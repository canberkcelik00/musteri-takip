<?php

use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Domains\CheckHostingPoints;
use App\Actions\Today\ListTodayTasks;
use App\Enums\CustomerServiceStatus;
use App\Enums\DnsRecordType;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Livewire\Pages\Today;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Models\User;
use App\Support\Dns\DnsLookup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Replaces DNS with A record answers from `$answers` (host => addresses, null = unreadable); a host that is
 * not listed has no record. The returned object records every host asked.
 *
 * @param  array<string, list<string>|null>  $answers
 * @return object{asked: list<string>}
 */
function fakeARecords(array $answers): object
{
    $lookup = new class($answers) extends DnsLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param  array<string, list<string>|null>  $answers */
        public function __construct(private readonly array $answers) {}

        public function records(string $host, DnsRecordType $type): ?array
        {
            $this->asked[] = $host;

            return array_key_exists($host, $this->answers) ? $this->answers[$host] : [];
        }
    };

    app()->instance(DnsLookup::class, $lookup);

    return $lookup;
}

/**
 * Replaces the server APIs: WHM lists one account per address in `$addresses`, cPanel one domain per
 * address; null makes both calls fail. Earlier fakes are dropped (the first matching one would win).
 *
 * @param  list<string>|null  $addresses
 */
function fakeSiteAddresses(?array $addresses): void
{
    Http::swap(new HttpFactory(app('events')));

    if ($addresses === null) {
        Http::fake(['*' => Http::response('', 500)]);

        return;
    }

    $accounts = array_map(fn (string $ip, int $index): array => ['user' => "hesap{$index}", 'domain' => "site{$index}.example", 'ip' => $ip], $addresses, array_keys($addresses));

    Http::fake([
        '*/json-api/listaccts*' => Http::response(['data' => ['acct' => $accounts], 'metadata' => ['result' => 1]]),
        '*/execute/DomainInfo/domains_data*' => Http::response(['status' => 1, 'data' => [
            'main_domain' => ['domain' => 'ana.example', 'ip' => $addresses[0] ?? ''],
            'addon_domains' => array_map(fn (string $ip): array => ['domain' => 'ek.example', 'ip' => $ip], $addresses),
            'parked_domains' => [],
        ]]),
    ]);
}

function pointsItem(?string $domain, ?Server $server, array $serviceAttributes = [], ?bool $cloudflare = false, ?Customer $customer = null): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->for($customer ?? Customer::factory()->create())
        ->create($serviceAttributes);

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain], 'server_id' => $server?->id]);
    $item->forceFill(['is_cloudflare' => $cloudflare])->saveQuietly();

    return $item->refresh();
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    $this->server = Server::factory()->create(['hostname' => 'Sunucu.Example.com']);
    fakeSiteAddresses([]);
});

describe('check', function () {
    it('says a domain points to the server when one of its addresses is the server\'s', function () {
        fakeARecords(['sunucu.example.com' => ['10.0.0.1', '10.0.0.2'], 'logi.com.tr' => ['10.0.0.2']]);
        $item = pointsItem(' Logi.com.TR ', $this->server);

        $result = app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeTrue()
            ->and($item->points_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed, $result->remaining])->toBe([1, 0, 0]);
    });

    it('says a domain does not point to the server when no address the server reports matches', function () {
        fakeSiteAddresses(['185.1.1.10']);
        fakeARecords(['sunucu.example.com' => ['185.1.1.1'], 'logi.com.tr' => ['203.0.113.9']]);
        $item = pointsItem('logi.com.tr', $this->server);

        app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeFalse();
    });

    it('counts a site on a shared address of the server as pointing to it, though the host name has another', function (string $type) {
        fakeSiteAddresses(['185.1.1.10', '185.1.1.11', '10.0.0.5']);
        fakeARecords(['sunucu.example.com' => ['185.1.1.1'], 'logi.com.tr' => ['185.1.1.11']]);
        $server = Server::factory()->{$type}()->create(['hostname' => 'sunucu.example.com']);
        $item = pointsItem('logi.com.tr', $server);

        app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeTrue();
    })->with(['whm', 'cpanel']);

    it('never says a domain points elsewhere when the server reports no public address', function (?array $reported) {
        fakeSiteAddresses($reported);
        fakeARecords(['sunucu.example.com' => ['185.1.1.1'], 'logi.com.tr' => ['203.0.113.9']]);
        $item = pointsItem('logi.com.tr', $this->server);

        $result = app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeNull()
            ->and($item->points_checked_at)->not->toBeNull()
            ->and($result->failed)->toBe(1);
    })->with([
        'server unreachable' => [null],
        'no addresses' => [[]],
        'only local addresses (NAT)' => [['10.0.0.5', '192.168.1.2']],
    ]);

    it('forgets the "points elsewhere" answers of the old rule once, so they are checked again', function () {
        $moved = pointsItem('tasindi.example', $this->server);
        $moved->forceFill(['points_to_server' => false, 'points_checked_at' => '2026-06-15 03:00:00'])->saveQuietly();
        $here = pointsItem('burada.example', $this->server);
        $here->forceFill(['points_to_server' => true, 'points_checked_at' => '2026-06-15 03:00:00'])->saveQuietly();

        (require base_path('database/migrations/2026_11_01_100000_recheck_hosting_points.php'))->up();

        expect($moved->refresh()->points_to_server)->toBeNull()
            ->and($moved->points_checked_at)->toBeNull()
            ->and($here->refresh()->points_to_server)->toBeTrue()
            ->and($here->points_checked_at)->not->toBeNull();
    });

    it('asks the server for its addresses once per run', function () {
        fakeSiteAddresses(['185.1.1.10']);
        fakeARecords(['sunucu.example.com' => ['185.1.1.1'], 'bir.example' => ['185.1.1.10'], 'iki.example' => ['185.1.1.10']]);
        pointsItem('bir.example', $this->server);
        pointsItem('iki.example', $this->server);

        app(CheckHostingPoints::class)->handle();

        Http::assertSentCount(1);
    });

    it('takes a server host name that already is an address as it is', function () {
        $lookup = fakeARecords(['logi.com.tr' => ['10.0.0.1']]);
        $item = pointsItem('logi.com.tr', Server::factory()->create(['hostname' => '10.0.0.1']));

        app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeTrue()->and($lookup->asked)->toBe(['logi.com.tr']);
    });

    it('leaves the answer unknown for a domain on Cloudflare or not yet checked, and clears a kept one', function () {
        $lookup = fakeARecords(['sunucu.example.com' => ['10.0.0.1'], 'cf.example' => ['104.16.0.1'], 'yeni.example' => ['104.16.0.1']]);
        $cloudflare = pointsItem('cf.example', $this->server, cloudflare: true);
        $cloudflare->forceFill(['points_to_server' => false])->saveQuietly();
        $unchecked = pointsItem('yeni.example', $this->server, cloudflare: null);

        $result = app(CheckHostingPoints::class)->handle();

        expect($lookup->asked)->toBe([])
            ->and($result->checked)->toBe(0)
            ->and($cloudflare->refresh()->points_to_server)->toBeNull()
            ->and($cloudflare->points_checked_at)->toBeNull()
            ->and($unchecked->refresh()->points_checked_at)->toBeNull();
    });

    it('skips items without a server, cancelled services, empty domains and non-hosting items', function () {
        $lookup = fakeARecords(['logi.com.tr' => ['10.0.0.1']]);
        $items = [
            pointsItem('logi.com.tr', null),
            pointsItem('logi.com.tr', $this->server, ['status' => CustomerServiceStatus::Cancelled]),
            pointsItem(null, $this->server),
            pointsItem('localhost', $this->server),
        ];
        $other = pointsItem('logi.com.tr', $this->server);
        $other->forceFill(['type' => ServiceType::Domain])->saveQuietly();

        $result = app(CheckHostingPoints::class)->handle();

        expect($lookup->asked)->toBe([])
            ->and($result->checked)->toBe(0)
            ->and(collect([...$items, $other])->map(fn (CustomerServiceItem $item) => $item->refresh()->points_checked_at)->filter()->all())->toBe([]);
    });

    it('keeps the earlier answer when a read fails but counts the attempt', function (array $answers) {
        fakeARecords($answers);
        $item = pointsItem('logi.com.tr', $this->server);
        $item->forceFill(['points_to_server' => false, 'points_checked_at' => '2026-06-01 09:00:00'])->saveQuietly();

        $result = app(CheckHostingPoints::class)->handle();

        expect($item->refresh()->points_to_server)->toBeFalse()
            ->and($item->points_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed])->toBe([0, 1]);
    })->with([
        'domain unreadable' => [['sunucu.example.com' => ['10.0.0.1'], 'logi.com.tr' => null]],
        'domain has no A record' => [['sunucu.example.com' => ['10.0.0.1']]],
        'server unreadable' => [['sunucu.example.com' => null, 'logi.com.tr' => ['10.0.0.1']]],
        'server has no A record' => [['logi.com.tr' => ['10.0.0.1']]],
    ]);

    it('checks again only after 24 hours', function () {
        $lookup = fakeARecords(['sunucu.example.com' => ['10.0.0.1'], 'logi.com.tr' => ['10.0.0.1']]);
        pointsItem('logi.com.tr', $this->server);

        app(CheckHostingPoints::class)->handle();
        $this->travelTo('2026-06-16 09:59:00');
        app(CheckHostingPoints::class)->handle();

        expect(collect($lookup->asked)->filter(fn (string $host): bool => $host === 'logi.com.tr'))->toHaveCount(1);

        $this->travelTo('2026-06-16 10:00:00');
        app(CheckHostingPoints::class)->handle();

        expect(collect($lookup->asked)->filter(fn (string $host): bool => $host === 'logi.com.tr'))->toHaveCount(2);
    });

    it('asks once for a domain used by several items of one server and once for the server', function () {
        $lookup = fakeARecords(['sunucu.example.com' => ['10.0.0.1'], 'logi.com.tr' => ['10.0.0.1'], 'diger.example' => ['10.0.0.1']]);
        $first = pointsItem('logi.com.tr', $this->server);
        $second = pointsItem('LOGI.com.tr', $this->server);
        pointsItem('diger.example', $this->server);

        app(CheckHostingPoints::class)->handle();

        expect(collect($lookup->asked)->countBy()->sortKeys()->all())->toBe(['diger.example' => 1, 'logi.com.tr' => 1, 'sunucu.example.com' => 1])
            ->and($first->refresh()->points_to_server)->toBeTrue()
            ->and($second->refresh()->points_to_server)->toBeTrue();
    });

    it('stops at the time budget and does the rest in the next run, never tried first', function () {
        $lookup = fakeARecords(['sunucu.example.com' => ['10.0.0.1']]);
        $old = pointsItem('eski.example', $this->server);
        $old->forceFill(['points_checked_at' => '2026-06-10 09:00:00'])->saveQuietly();
        $fresh = pointsItem('yeni.example', $this->server);

        $first = app(CheckHostingPoints::class)->handle(0);

        expect(collect($lookup->asked)->contains('yeni.example'))->toBeTrue()
            ->and(collect($lookup->asked)->contains('eski.example'))->toBeFalse()
            ->and($first->remaining)->toBe(1)
            ->and($fresh->refresh()->points_checked_at)->not->toBeNull()
            ->and($old->refresh()->points_checked_at->toDateTimeString())->toBe('2026-06-10 09:00:00');

        $second = app(CheckHostingPoints::class)->handle(0);

        expect(collect($lookup->asked)->contains('eski.example'))->toBeTrue()->and($second->remaining)->toBe(0);
    });

    it('forgets the answer when the item gets another domain or server', function () {
        fakeARecords(['sunucu.example.com' => ['10.0.0.1'], 'logi.com.tr' => ['10.0.0.1']]);
        $item = pointsItem('logi.com.tr', $this->server);
        app(CheckHostingPoints::class)->handle();
        expect($item->refresh()->points_to_server)->toBeTrue();

        app(UpdateCustomerService::class)->handle($item->customerService, [
            'items' => [$item->id => ['details' => ['domain' => 'baska.com']]],
        ]);

        expect($item->refresh()->points_to_server)->toBeNull()->and($item->points_checked_at)->toBeNull();

        $item->forceFill(['points_to_server' => true, 'points_checked_at' => '2026-06-15 03:00:00'])->saveQuietly();
        $item->update(['server_id' => Server::factory()->create()->id]);

        expect($item->refresh()->points_to_server)->toBeNull()->and($item->points_checked_at)->toBeNull();
    });

    it('keeps the answer when the item is saved with the same domain', function () {
        fakeARecords(['sunucu.example.com' => ['10.0.0.1'], 'logi.com.tr' => ['10.0.0.1']]);
        $item = pointsItem('logi.com.tr', $this->server);
        app(CheckHostingPoints::class)->handle();

        app(UpdateCustomerService::class)->handle($item->customerService, [
            'items' => [$item->id => ['details' => ['domain' => ' Logi.com.tr ']]],
        ]);

        expect($item->refresh()->points_to_server)->toBeTrue();
    });
});

describe('command', function () {
    it('runs on every scheduler call without overlapping', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:check-hosting-points'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });

    it('finishes successfully and reports what it did', function () {
        fakeARecords([]);

        $this->artisan('app:check-hosting-points')
            ->expectsOutputToContain(__('domains.points_check_result', ['checked' => 0, 'failed' => 0, 'remaining' => 0]))
            ->assertSuccessful();
    });
});

describe('today', function () {
    function movedItem(string $domain, Server $server, array $serviceAttributes = [], ?Customer $customer = null, ?bool $points = false): CustomerServiceItem
    {
        $item = pointsItem($domain, $server, $serviceAttributes, customer: $customer);
        $item->forceFill(['points_to_server' => $points, 'points_checked_at' => '2026-06-15 03:00:00'])->saveQuietly();

        return $item;
    }

    it('lists only hostings that point elsewhere, of active services and customers not archived', function () {
        $moved = movedItem('tasindi.example', $this->server);
        movedItem('burada.example', $this->server, points: true);
        movedItem('bilinmiyor.example', $this->server, points: null);
        movedItem('iptal.example', $this->server, ['status' => CustomerServiceStatus::Cancelled]);
        movedItem('arsiv.example', $this->server, customer: Customer::factory()->archived()->create());

        $section = app(ListTodayTasks::class)->handle(User::factory()->admin()->create())['moved'];

        expect($section['items']->pluck('id')->all())->toBe([$moved->id])->and($section['total'])->toBe(1);
    });

    it('shows the row with its state and a button to the window', function () {
        movedItem('tasindi.example', $this->server);

        $html = Livewire::actingAs(User::factory()->admin()->create())->test(Today::class)
            ->assertSee(__('today.moved_subject', ['domain' => 'tasindi.example']))
            ->assertSee(__('today.moved_status'))
            ->html();

        expect($html)->toContain("name: 'check-domain', params: { domain: 'tasindi.example' }");
    });

    it('is hidden from a user without the services permission', function () {
        movedItem('tasindi.example', $this->server);
        $developer = User::factory()->developer()->create();
        Role::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

        $section = app(ListTodayTasks::class)->handle($developer->refresh())['moved'];

        expect($section['items'])->toHaveCount(0)->and($section['total'])->toBe(0);
    });
});
