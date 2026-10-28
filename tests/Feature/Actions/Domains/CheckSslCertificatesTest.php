<?php

use App\Actions\Calendar\ListCalendarEntries;
use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Domains\CheckSslCertificates;
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
use App\Models\User;
use App\Support\Dns\TlsCertificate;
use App\Support\Dns\TlsCertificateLookup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Replaces the certificate reader with answers from `$answers` (host => expiry date or null = unreadable); a host
 * that is not listed is unreadable. The returned object records every host asked.
 *
 * @param  array<string, string|null>  $answers
 * @return object{asked: list<string>}
 */
function fakeCertificates(array $answers): object
{
    $lookup = new class($answers) extends TlsCertificateLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param  array<string, string|null>  $answers */
        public function __construct(private readonly array $answers) {}

        public function certificateOf(string $host, int $timeoutSeconds = 5): ?TlsCertificate
        {
            $this->asked[] = $host;
            $expires = $this->answers[$host] ?? null;

            return $expires === null ? null : new TlsCertificate(CarbonImmutable::parse($expires, 'UTC'), 'Let\'s Encrypt', $host);
        }
    };

    app()->instance(TlsCertificateLookup::class, $lookup);

    return $lookup;
}

function sslItem(?string $domain, array $serviceAttributes = [], ?Customer $customer = null): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->for($customer ?? Customer::factory()->create())
        ->create($serviceAttributes);

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain]]);

    return $item->refresh();
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

describe('check', function () {
    it('keeps the expiry of the certificate and the time of the attempt', function () {
        fakeCertificates(['logi.com.tr' => '2026-07-01 12:00:00']);
        $item = sslItem(' Logi.com.TR ');

        $result = app(CheckSslCertificates::class)->handle();

        expect($item->refresh()->ssl_expires_at->toDateTimeString())->toBe('2026-07-01 15:00:00')
            ->and($item->ssl_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed, $result->remaining])->toBe([1, 0, 0]);
    });

    it('keeps the earlier expiry when the read fails but counts the attempt', function () {
        fakeCertificates(['logi.com.tr' => null]);
        $item = sslItem('logi.com.tr');
        $item->forceFill(['ssl_expires_at' => '2026-06-20 00:00:00', 'ssl_checked_at' => '2026-06-01 09:00:00'])->saveQuietly();

        $result = app(CheckSslCertificates::class)->handle();

        expect($item->refresh()->ssl_expires_at->toDateTimeString())->toBe('2026-06-20 00:00:00')
            ->and($item->ssl_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed])->toBe([0, 1]);
    });

    it('reads a domain again only after 24 hours', function () {
        $lookup = fakeCertificates(['logi.com.tr' => '2026-08-01 00:00:00']);
        sslItem('logi.com.tr');

        app(CheckSslCertificates::class)->handle();
        $this->travelTo('2026-06-16 09:59:00');
        app(CheckSslCertificates::class)->handle();

        expect($lookup->asked)->toHaveCount(1);

        $this->travelTo('2026-06-16 10:00:00');
        app(CheckSslCertificates::class)->handle();

        expect($lookup->asked)->toHaveCount(2);
    });

    it('reads a domain used by several items once and writes it to all of them', function () {
        $lookup = fakeCertificates(['logi.com.tr' => '2026-08-01 00:00:00']);
        $first = sslItem('logi.com.tr');
        $second = sslItem('LOGI.com.tr');

        app(CheckSslCertificates::class)->handle();

        expect($lookup->asked)->toBe(['logi.com.tr'])
            ->and($first->refresh()->ssl_expires_at)->not->toBeNull()
            ->and($second->refresh()->ssl_expires_at)->not->toBeNull();
    });

    it('skips cancelled services, empty domains and values that are not host names', function () {
        $lookup = fakeCertificates(['logi.com.tr' => '2026-08-01 00:00:00']);
        $items = [
            sslItem('logi.com.tr', ['status' => CustomerServiceStatus::Cancelled]),
            sslItem(null),
            sslItem('logi com/tr'),
            sslItem('localhost'),
        ];

        $result = app(CheckSslCertificates::class)->handle();

        expect($lookup->asked)->toBe([])
            ->and($result->checked)->toBe(0)
            ->and(collect($items)->map(fn (CustomerServiceItem $item) => $item->refresh()->ssl_checked_at)->filter()->all())->toBe([]);
    });

    it('stops at the time budget and does the rest in the next run, never tried first', function () {
        $lookup = fakeCertificates([]);
        $old = sslItem('eski.example');
        $old->forceFill(['ssl_checked_at' => '2026-06-10 09:00:00'])->saveQuietly();
        $fresh = sslItem('yeni.example');

        $first = app(CheckSslCertificates::class)->handle(0);

        expect($lookup->asked)->toBe(['yeni.example'])
            ->and($first->remaining)->toBe(1)
            ->and($fresh->refresh()->ssl_checked_at)->not->toBeNull()
            ->and($old->refresh()->ssl_checked_at->toDateTimeString())->toBe('2026-06-10 09:00:00');

        $second = app(CheckSslCertificates::class)->handle(0);

        expect($lookup->asked)->toBe(['yeni.example', 'eski.example'])->and($second->remaining)->toBe(0);
    });

    it('forgets the certificate when the item gets another domain', function () {
        fakeCertificates(['logi.com.tr' => '2026-08-01 00:00:00']);
        $item = sslItem('logi.com.tr');
        app(CheckSslCertificates::class)->handle();
        expect($item->refresh()->ssl_expires_at)->not->toBeNull();

        app(UpdateCustomerService::class)->handle($item->customerService, [
            'items' => [$item->id => ['details' => ['domain' => 'baska.com']]],
        ]);

        expect($item->refresh()->ssl_expires_at)->toBeNull()->and($item->ssl_checked_at)->toBeNull();
    });

    it('keeps the certificate when the item is saved with the same domain', function () {
        fakeCertificates(['logi.com.tr' => '2026-08-01 00:00:00']);
        $item = sslItem('logi.com.tr');
        app(CheckSslCertificates::class)->handle();

        app(UpdateCustomerService::class)->handle($item->customerService, [
            'items' => [$item->id => ['details' => ['domain' => ' Logi.com.tr ']]],
        ]);

        expect($item->refresh()->ssl_expires_at)->not->toBeNull();
    });
});

describe('command', function () {
    it('runs on every scheduler call without overlapping', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:check-ssl'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });

    it('finishes successfully and reports what it did', function () {
        fakeCertificates([]);

        $this->artisan('app:check-ssl')
            ->expectsOutputToContain(__('domains.ssl_check_result', ['checked' => 0, 'failed' => 0, 'remaining' => 0]))
            ->assertSuccessful();
    });
});

describe('today', function () {
    function sslWithExpiry(string $domain, string $expires, array $serviceAttributes = [], ?Customer $customer = null): CustomerServiceItem
    {
        $item = sslItem($domain, $serviceAttributes, $customer);
        $item->forceFill(['ssl_expires_at' => $expires, 'ssl_checked_at' => '2026-06-15 03:00:00'])->saveQuietly();

        return $item;
    }

    it('lists certificates ending within 14 days or already ended, the soonest first', function () {
        $later = sslWithExpiry('gec.example', '2026-06-29 09:00:00');
        $ended = sslWithExpiry('bitti.example', '2026-06-10 00:00:00');
        sslWithExpiry('uzak.example', '2026-06-30 12:00:00');
        sslWithExpiry('iptal.example', '2026-06-20 00:00:00', ['status' => CustomerServiceStatus::Cancelled]);
        sslWithExpiry('arsiv.example', '2026-06-20 00:00:00', customer: Customer::factory()->archived()->create());
        sslItem('okunmadi.example');

        $section = app(ListTodayTasks::class)->handle(User::factory()->admin()->create())['ssl'];

        expect($section['items']->pluck('id')->all())->toBe([$ended->id, $later->id])
            ->and($section['total'])->toBe(2);
    });

    it('shows the rows with their state and a button to the window', function () {
        sslWithExpiry('bitti.example', '2026-06-10 00:00:00');
        sslWithExpiry('yakin.example', '2026-06-18 12:00:00');

        $html = Livewire::actingAs(User::factory()->admin()->create())->test(Today::class)
            ->assertSee(__('today.ssl_subject', ['domain' => 'yakin.example']))
            ->assertSee(__('today.ssl_expired'))
            ->assertSee(__('today.ssl_days_left', ['count' => 3]))
            ->html();

        expect($html)->toContain("name: 'check-domain', params: { domain: 'yakin.example' }");
    });

    it('is hidden from a user without the services permission and runs no query for them', function () {
        sslWithExpiry('yakin.example', '2026-06-18 12:00:00');
        $developer = User::factory()->developer()->create();
        Role::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);

        $section = app(ListTodayTasks::class)->handle($developer->refresh())['ssl'];

        expect($section['items'])->toHaveCount(0)->and($section['total'])->toBe(0);
    });
});

describe('calendar', function () {
    it('puts the end of a certificate on the calendar as an amber warning', function () {
        $item = sslItem('logi.com.tr');
        $item->forceFill(['ssl_expires_at' => '2026-06-20 23:30:00'])->saveQuietly();
        sslItem('iptal.example', ['status' => CustomerServiceStatus::Cancelled])
            ->forceFill(['ssl_expires_at' => '2026-06-20 10:00:00'])->saveQuietly();
        sslItem('disarida.example')->forceFill(['ssl_expires_at' => '2026-07-20 10:00:00'])->saveQuietly();

        $entries = app(ListCalendarEntries::class)
            ->handle(User::factory()->admin()->create(), Carbon::parse('2026-06-15'), Carbon::parse('2026-06-21'))
            ->all();

        expect($entries)->toHaveCount(1)
            ->and($entries[0]->category)->toBe(CalendarCategory::AlertSoon)
            ->and($entries[0]->title)->toBe(__('calendar.entry.ssl_end', ['domain' => 'logi.com.tr']))
            ->and($entries[0]->date->toDateString())->toBe('2026-06-20')
            ->and($entries[0]->url)->toBe(route('app.customers.show', $item->customerService->customer_id));
    });

    it('does not show it to a user without the services permission or under "only mine"', function () {
        sslItem('logi.com.tr')->forceFill(['ssl_expires_at' => '2026-06-20 10:00:00'])->saveQuietly();
        $developer = User::factory()->developer()->create();
        Role::findByName('developer')->revokePermissionTo(Permission::ServicesView->value);
        $calendar = fn (User $user, bool $mine = false): int => app(ListCalendarEntries::class)
            ->handle($user, Carbon::parse('2026-06-15'), Carbon::parse('2026-06-21'), null, $mine)->count();

        expect($calendar($developer->refresh()))->toBe(0)
            ->and($calendar(User::factory()->admin()->create(), true))->toBe(0);
    });
});
