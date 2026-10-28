<?php

use App\Actions\Calendar\ListCalendarEntries;
use App\Actions\Digest\BuildDigest;
use App\Actions\Domains\ProbeSites;
use App\Actions\Today\ListTodayTasks;
use App\Enums\CalendarCategory;
use App\Enums\CustomerServiceStatus;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Livewire\Pages\Today;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\SiteIncident;
use App\Models\User;
use App\Support\Web\HttpProbe;
use App\Support\Web\ProbeResult;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Replaces the site probe with answers from `$answers` (host => ProbeResult, null = cannot be asked); a host that is not
 * listed is up. The returned object records every host asked and its answers can be changed between runs.
 *
 * @param  array<string, ProbeResult|null>  $answers
 * @return object{asked: list<string>, answers: array<string, ProbeResult|null>}
 */
function fakeSiteProbe(array $answers = []): object
{
    $probe = new class($answers) extends HttpProbe
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param  array<string, ProbeResult|null>  $answers */
        public function __construct(public array $answers) {}

        public function probe(string $host): ?ProbeResult
        {
            $this->asked[] = $host;

            return array_key_exists($host, $this->answers) ? $this->answers[$host] : new ProbeResult(true, 200, null);
        }
    };

    app()->instance(HttpProbe::class, $probe);

    return $probe;
}

function siteDown(string $reason = 'HTTP 503'): ProbeResult
{
    return new ProbeResult(false, 503, $reason);
}

function siteItem(?string $domain, array $serviceAttributes = [], ?Customer $customer = null, ServiceType $type = ServiceType::Hosting): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => $type]))
        ->for($customer ?? Customer::factory()->create())
        ->create($serviceAttributes);

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain]]);

    return $item->refresh();
}

function downIncident(string $domain, array $serviceAttributes = [], ?Customer $customer = null, array $incident = []): SiteIncident
{
    $item = siteItem($domain, $serviceAttributes, $customer);

    return SiteIncident::factory()->create([...$incident, 'customer_service_item_id' => $item->id, 'domain' => $domain]);
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

describe('probe', function () {
    it('opens no incident for a single failed probe', function () {
        fakeSiteProbe(['logi.com.tr' => siteDown()]);
        $item = siteItem(' Logi.com.TR ');

        $result = app(ProbeSites::class)->handle();

        expect($item->refresh()->site_failures)->toBe(1)
            ->and($item->site_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and(SiteIncident::query()->count())->toBe(0)
            ->and([$result->checked, $result->failed, $result->remaining])->toBe([1, 0, 0]);
    });

    it('opens an incident at the first failure once the second one follows', function () {
        fakeSiteProbe(['logi.com.tr' => siteDown('HTTP 503')]);
        $item = siteItem('logi.com.tr');

        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:15:00');
        app(ProbeSites::class)->handle();

        $incident = SiteIncident::query()->sole();

        expect($item->refresh()->site_failures)->toBe(2)
            ->and($incident->customer_service_item_id)->toBe($item->id)
            ->and($incident->domain)->toBe('logi.com.tr')
            ->and($incident->reason)->toBe('HTTP 503')
            ->and($incident->started_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and($incident->ended_at)->toBeNull();
    });

    it('does not open a second incident while the site stays down', function () {
        fakeSiteProbe(['logi.com.tr' => siteDown()]);
        siteItem('logi.com.tr');

        foreach (['10:00', '10:15', '10:30', '10:45'] as $time) {
            $this->travelTo("2026-06-15 {$time}:00");
            app(ProbeSites::class)->handle();
        }

        expect(SiteIncident::query()->count())->toBe(1);
    });

    it('closes the incident on the first answer and resets the counter', function () {
        $probe = fakeSiteProbe(['logi.com.tr' => siteDown()]);
        $item = siteItem('logi.com.tr');

        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:15:00');
        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:30:00');
        $probe->answers = [];
        app(ProbeSites::class)->handle();

        $incident = SiteIncident::query()->sole();

        expect($incident->ended_at->toDateTimeString())->toBe('2026-06-15 10:30:00')
            ->and($incident->started_at->diffInMinutes($incident->ended_at))->toBe(30.0)
            ->and($item->refresh()->site_failures)->toBe(0);
    });

    it('lets an answer wipe out a single failure', function () {
        $probe = fakeSiteProbe(['logi.com.tr' => siteDown()]);
        $item = siteItem('logi.com.tr');

        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:15:00');
        $probe->answers = [];
        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:30:00');
        $probe->answers = ['logi.com.tr' => siteDown()];
        app(ProbeSites::class)->handle();

        expect($item->refresh()->site_failures)->toBe(1)->and(SiteIncident::query()->count())->toBe(0);
    });

    it('opens a new incident when the site goes down again after an earlier one ended', function () {
        $probe = fakeSiteProbe(['logi.com.tr' => siteDown()]);
        siteItem('logi.com.tr');

        foreach (['10:00', '10:15'] as $time) {
            $this->travelTo("2026-06-15 {$time}:00");
            app(ProbeSites::class)->handle();
        }
        $probe->answers = [];
        $this->travelTo('2026-06-15 10:30:00');
        app(ProbeSites::class)->handle();
        $probe->answers = ['logi.com.tr' => siteDown()];

        foreach (['10:45', '11:00'] as $time) {
            $this->travelTo("2026-06-15 {$time}:00");
            app(ProbeSites::class)->handle();
        }

        expect(SiteIncident::query()->count())->toBe(2)->and(SiteIncident::query()->open()->count())->toBe(1);
    });

    it('changes nothing but the attempt time for a host that cannot be asked', function () {
        fakeSiteProbe(['logi.com.tr' => null]);
        $item = siteItem('logi.com.tr');
        $item->forceFill(['site_failures' => 1])->saveQuietly();

        $result = app(ProbeSites::class)->handle();

        expect($item->refresh()->site_failures)->toBe(1)
            ->and($item->site_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed])->toBe([0, 1]);
    });

    it('asks a domain again only after 14 minutes, so a cron every 15 minutes never skips one', function () {
        $probe = fakeSiteProbe();
        siteItem('logi.com.tr');

        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:13:59');
        app(ProbeSites::class)->handle();
        $this->travelTo('2026-06-15 10:14:59');
        app(ProbeSites::class)->handle();

        expect($probe->asked)->toBe(['logi.com.tr', 'logi.com.tr']);
    });

    it('asks a domain shared by two items once and records the answer on both', function () {
        $probe = fakeSiteProbe(['ortak.example' => siteDown()]);
        $first = siteItem('ortak.example');
        $second = siteItem('Ortak.Example');

        app(ProbeSites::class)->handle();

        expect($probe->asked)->toBe(['ortak.example'])
            ->and($first->refresh()->site_failures)->toBe(1)
            ->and($second->refresh()->site_failures)->toBe(1);
    });

    it('skips items without a valid domain, of other types and of services that are not active', function () {
        $probe = fakeSiteProbe();
        siteItem(null);
        siteItem('gecersiz domain');
        siteItem('alanadi.example', type: ServiceType::Domain);
        siteItem('iptal.example', ['status' => CustomerServiceStatus::Cancelled]);

        $result = app(ProbeSites::class)->handle();

        expect($probe->asked)->toBe([])->and($result->checked)->toBe(0);
    });

    it('stops at the time budget and leaves the rest for the next run', function () {
        $probe = fakeSiteProbe();
        siteItem('bir.example');
        siteItem('iki.example');
        siteItem('uc.example');

        $first = app(ProbeSites::class)->handle(0);
        $second = app(ProbeSites::class)->handle(0);

        expect($first->checked)->toBe(1)->and($first->remaining)->toBe(2)
            ->and($second->checked)->toBe(1)->and($second->remaining)->toBe(1)
            ->and(array_unique($probe->asked))->toHaveCount(2);
    });

    it('starts with the domain never probed, then the oldest probe', function () {
        $probe = fakeSiteProbe();
        $old = siteItem('eski.example');
        $old->forceFill(['site_checked_at' => '2026-06-15 08:00:00'])->saveQuietly();
        $older = siteItem('daha-eski.example');
        $older->forceFill(['site_checked_at' => '2026-06-15 07:00:00'])->saveQuietly();
        siteItem('yeni.example');

        app(ProbeSites::class)->handle();

        expect($probe->asked)->toBe(['yeni.example', 'daha-eski.example', 'eski.example']);
    });

    it('resets the counter when the domain changes', function () {
        $item = siteItem('eski.example');
        $item->forceFill(['site_failures' => 1, 'site_checked_at' => '2026-06-15 09:00:00'])->saveQuietly();

        $item->update(['details' => ['domain' => 'yeni.example']]);

        expect($item->refresh()->site_failures)->toBe(0)->and($item->site_checked_at)->toBeNull();
    });

    it('ends an open incident whose domain changed or whose service is no longer active', function () {
        fakeSiteProbe(['surer.example' => siteDown()]);
        $changed = siteItem('eski.example');
        $changedIncident = SiteIncident::factory()->create(['customer_service_item_id' => $changed->id, 'domain' => 'eski.example']);
        $changed->update(['details' => ['domain' => 'yeni.example']]);
        $cancelled = siteItem('iptal.example');
        $cancelledIncident = SiteIncident::factory()->create(['customer_service_item_id' => $cancelled->id, 'domain' => 'iptal.example']);
        $cancelled->customerService->update(['status' => CustomerServiceStatus::Cancelled]);
        $running = siteItem('surer.example');
        $runningIncident = SiteIncident::factory()->create(['customer_service_item_id' => $running->id, 'domain' => 'surer.example']);

        app(ProbeSites::class)->handle();

        expect($changedIncident->refresh()->ended_at)->not->toBeNull()
            ->and($cancelledIncident->refresh()->ended_at)->not->toBeNull()
            ->and($runningIncident->refresh()->ended_at)->toBeNull();
    });
});

describe('command', function () {
    it('runs on every scheduler call without overlapping', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:probe-sites'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });

    it('finishes successfully and reports what it did', function () {
        fakeSiteProbe();

        $this->artisan('app:probe-sites')
            ->expectsOutputToContain(__('domains.sites_probe_result', ['checked' => 0, 'failed' => 0, 'remaining' => 0]))
            ->assertSuccessful();
    });
});

describe('today', function () {
    it('lists only sites that are still down, of active services and customers not archived, the longest down first', function () {
        $later = downIncident('sonra.example', incident: ['started_at' => '2026-06-15 09:00:00']);
        $first = downIncident('once.example', incident: ['started_at' => '2026-06-15 06:00:00']);
        downIncident('acildi.example', incident: ['ended_at' => '2026-06-15 09:30:00']);
        downIncident('iptal.example', ['status' => CustomerServiceStatus::Cancelled]);
        downIncident('arsiv.example', customer: Customer::factory()->archived()->create());

        $section = app(ListTodayTasks::class)->handle(User::factory()->admin()->create())['down'];

        expect($section['items']->pluck('id')->all())->toBe([$first->id, $later->id])->and($section['total'])->toBe(2);
    });

    it('shows the row with how long the site has been down and a button to the window', function () {
        downIncident('kapali.example', incident: ['started_at' => '2026-06-15 07:00:00']);

        $html = Livewire::actingAs(User::factory()->admin()->create())->test(Today::class)
            ->assertSee(__('today.down_subject', ['domain' => 'kapali.example']))
            ->assertSee('15.06.2026 07:00')
            ->html();

        expect($html)->toContain("name: 'check-domain', params: { domain: 'kapali.example' }");
    });

    it('is hidden from a user without the services permission', function () {
        downIncident('kapali.example');
        $developer = User::factory()->developer()->create();
        Role::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

        $section = app(ListTodayTasks::class)->handle($developer->refresh())['down'];

        expect($section['items'])->toHaveCount(0)->and($section['total'])->toBe(0);
    });
});

describe('calendar', function () {
    function calendarDay(User $user, string $day = '2026-06-15', ?array $categories = null): Collection
    {
        return app(ListCalendarEntries::class)->handle($user, Carbon::parse($day), Carbon::parse($day), $categories);
    }

    it('shows a red "closed" record at the start and a green "opened" record at the end', function () {
        $incident = downIncident('kapali.example', incident: ['started_at' => '2026-06-15 08:30:00', 'ended_at' => '2026-06-15 09:45:00', 'reason' => 'HTTP 503']);

        $entries = calendarDay(User::factory()->admin()->create())->keyBy('key');

        $down = $entries['site-down:'.$incident->id];
        $up = $entries['site-up:'.$incident->id];

        expect($down->title)->toBe(__('calendar.entry.site_down', ['domain' => 'kapali.example']))
            ->and($down->category)->toBe(CalendarCategory::AlertDown)
            ->and($down->color())->toBe('red')
            ->and($down->start)->toBe('08:30')
            ->and($down->recorded)->toBeTrue()
            ->and($up->title)->toBe(__('calendar.entry.site_up', ['domain' => 'kapali.example']))
            ->and($up->color())->toBe('green')
            ->and($up->start)->toBe('09:45')
            ->and($up->detail)->toContain(__('calendar.entry.site_down_for', ['duration' => '1 saat']));
    });

    it('shows only the closed record for a site that is still down, and each record on its own day', function () {
        $incident = downIncident('kapali.example', incident: ['started_at' => '2026-06-14 23:30:00', 'ended_at' => '2026-06-15 00:30:00']);
        $user = User::factory()->admin()->create();

        expect(calendarDay($user, '2026-06-14')->pluck('key')->all())->toBe(['site-down:'.$incident->id])
            ->and(calendarDay($user, '2026-06-15')->pluck('key')->all())->toBe(['site-up:'.$incident->id]);
    });

    it('leaves the records out for the category filter and for a user without the services permission', function () {
        downIncident('kapali.example', incident: ['started_at' => '2026-06-15 08:30:00']);
        $developer = User::factory()->developer()->create();
        Role::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

        expect(calendarDay(User::factory()->admin()->create(), categories: [CalendarCategory::Event]))->toHaveCount(0)
            ->and(calendarDay($developer->refresh()))->toHaveCount(0);
    });
});

describe('digest', function () {
    function digestNow(): array
    {
        $now = Carbon::now('Europe/Istanbul');

        return app(BuildDigest::class)->handle($now->copy()->subDay(), $now);
    }

    it('lists the sites that are down right now, even from before the last digest', function () {
        $incident = downIncident('kapali.example', incident: ['started_at' => '2026-06-01 08:00:00']);
        downIncident('acildi.example', incident: ['ended_at' => '2026-06-15 09:00:00']);
        downIncident('iptal.example', ['status' => CustomerServiceStatus::Cancelled]);

        $digest = digestNow();

        expect($digest['down_sites']->pluck('id')->all())->toBe([$incident->id])
            ->and(BuildDigest::isEmpty($digest))->toBeFalse();
    });

    it('is empty when every site answers', function () {
        downIncident('acildi.example', incident: ['ended_at' => '2026-06-15 09:00:00']);

        expect(digestNow()['down_sites'])->toBeEmpty()->and(BuildDigest::isEmpty(digestNow()))->toBeTrue();
    });
});
