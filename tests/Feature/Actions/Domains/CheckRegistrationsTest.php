<?php

use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Domains\CheckDomainRegistration;
use App\Actions\Domains\CheckRegistrations;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Livewire\Windows\CheckDomain;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\User;
use App\Support\Dns\NameserverLookup;
use App\Support\Dns\Registration;
use App\Support\Dns\RegistrationLookup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

/**
 * Replaces the registration reader with answers from `$answers` (host => [expiry day, registrar] or null =
 * unreadable); a host that is not listed is unreadable. The returned object records every host asked.
 *
 * @param  array<string, array{0: string|null, 1: string|null}|null>  $answers
 * @return object{asked: list<string>}
 */
function fakeRegistrations(array $answers): object
{
    $lookup = new class($answers) extends RegistrationLookup
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param  array<string, array{0: string|null, 1: string|null}|null>  $answers */
        public function __construct(private readonly array $answers) {}

        public function lookup(string $domain): ?Registration
        {
            $this->asked[] = $domain;
            $answer = $this->answers[$domain] ?? null;

            return $answer === null ? null : new Registration($answer[0] === null ? null : CarbonImmutable::parse($answer[0]), $answer[1], $answer[2] ?? []);
        }
    };

    app()->instance(RegistrationLookup::class, $lookup);

    return $lookup;
}

/**
 * Replaces the live NS reader: the same answer for every host (null = the lookup failed).
 *
 * @param  list<string>|null  $answer
 */
function fakeLiveNameservers(?array $answer): void
{
    app()->instance(NameserverLookup::class, new class($answer) extends NameserverLookup
    {
        /** @param  list<string>|null  $answer */
        public function __construct(private readonly ?array $answer) {}

        public function nameserversOf(string $host): ?array
        {
            return $this->answer;
        }
    });
}

/**
 * @param  array<string, mixed>  $serviceAttributes
 */
function domainServiceItem(?string $domain, array $serviceAttributes = [], ?ServiceType $type = null): CustomerServiceItem
{
    $service = CustomerService::factory()
        ->forCatalogService(CatalogService::factory()->create(['type' => $type ?? ServiceType::Domain]))
        ->for(Customer::query()->where('short_name', 'Logi')->first() ?? Customer::factory()->create(['short_name' => 'Logi']))
        ->create($serviceAttributes);

    $item = $service->items()->firstOrFail();
    $item->update(['details' => ['domain' => $domain]]);

    return $item->refresh();
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    fakeLiveNameservers(null);
});

describe('check', function () {
    it('keeps the registration end, the registrar and the time of the attempt', function () {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', 'Natro']]);
        $item = domainServiceItem(' Logi.com.TR ');

        $result = app(CheckRegistrations::class)->handle();

        expect($item->refresh()->registration_expires_at->toDateString())->toBe('2027-03-04')
            ->and($item->registrar)->toBe('Natro')
            ->and($item->registration_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed, $result->remaining])->toBe([1, 0, 0]);
    });

    it('keeps what was known when the read fails but counts the attempt', function () {
        fakeRegistrations(['logi.com.tr' => null]);
        $item = domainServiceItem('logi.com.tr');
        $item->forceFill(['registration_expires_at' => '2026-09-01', 'registrar' => 'Natro', 'registration_checked_at' => '2026-06-01 09:00:00'])->saveQuietly();

        $result = app(CheckRegistrations::class)->handle();

        expect($item->refresh()->registration_expires_at->toDateString())->toBe('2026-09-01')
            ->and($item->registrar)->toBe('Natro')
            ->and($item->registration_checked_at->toDateTimeString())->toBe('2026-06-15 10:00:00')
            ->and([$result->checked, $result->failed])->toBe([0, 1]);
    });

    it('reads a domain again only after 24 hours', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        domainServiceItem('logi.com.tr');

        app(CheckRegistrations::class)->handle();
        $this->travelTo('2026-06-16 09:59:00');
        app(CheckRegistrations::class)->handle();

        expect($lookup->asked)->toHaveCount(1);

        $this->travelTo('2026-06-16 10:00:00');
        app(CheckRegistrations::class)->handle();

        expect($lookup->asked)->toHaveCount(2);
    });

    it('reads a domain used by several items once and writes it to all of them', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        $first = domainServiceItem('logi.com.tr');
        $second = domainServiceItem('LOGI.com.tr');

        app(CheckRegistrations::class)->handle();

        expect($lookup->asked)->toBe(['logi.com.tr'])
            ->and($first->refresh()->registration_expires_at)->not->toBeNull()
            ->and($second->refresh()->registration_expires_at)->not->toBeNull();
    });

    it('skips cancelled services, other service types, empty domains and values that are not host names', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        $items = [
            domainServiceItem('logi.com.tr', ['status' => CustomerServiceStatus::Cancelled]),
            domainServiceItem('logi.com.tr', type: ServiceType::Hosting),
            domainServiceItem(null),
            domainServiceItem('logi com/tr'),
            domainServiceItem('localhost'),
        ];

        $result = app(CheckRegistrations::class)->handle();

        expect($lookup->asked)->toBe([])
            ->and($result->checked)->toBe(0)
            ->and(collect($items)->map(fn (CustomerServiceItem $item) => $item->refresh()->registration_checked_at)->filter()->all())->toBe([]);
    });

    it('stops at the time budget and does the rest in the next run, never tried first', function () {
        $lookup = fakeRegistrations([]);
        $old = domainServiceItem('eski.example');
        $old->forceFill(['registration_checked_at' => '2026-06-10 09:00:00'])->saveQuietly();
        $fresh = domainServiceItem('yeni.example');

        $first = app(CheckRegistrations::class)->handle(0);

        expect($lookup->asked)->toBe(['yeni.example'])
            ->and($first->remaining)->toBe(1)
            ->and($fresh->refresh()->registration_checked_at)->not->toBeNull()
            ->and($old->refresh()->registration_checked_at->toDateTimeString())->toBe('2026-06-10 09:00:00');

        $second = app(CheckRegistrations::class)->handle(0);

        expect($lookup->asked)->toBe(['yeni.example', 'eski.example'])->and($second->remaining)->toBe(0);
    });

    it('forgets the registration when the item gets another domain', function () {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', 'Natro']]);
        $item = domainServiceItem('logi.com.tr');
        app(CheckRegistrations::class)->handle();
        expect($item->refresh()->registration_expires_at)->not->toBeNull();

        app(UpdateCustomerService::class)->handle($item->customerService, [
            'items' => [$item->id => ['details' => ['domain' => 'baska.com']]],
        ]);

        expect($item->refresh()->registration_expires_at)->toBeNull()
            ->and($item->registrar)->toBeNull()
            ->and($item->registration_checked_at)->toBeNull();
    });
});

describe('command', function () {
    it('runs on every scheduler call without overlapping', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:check-registration'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });

    it('finishes successfully and reports what it did', function () {
        fakeRegistrations([]);

        $this->artisan('app:check-registration')
            ->expectsOutputToContain(__('domains.registration_check_result', ['checked' => 0, 'failed' => 0, 'remaining' => 0]))
            ->assertSuccessful();
    });
});

describe('window', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('does not read WHOIS until the tab asks for it', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', 'Natro']]);

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])->assertSet('registrationLoaded', false);

        expect($lookup->asked)->toBe([]);
    });

    it('shows the registration end and the registrar once, however often the tab is opened', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', 'Natro']]);

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
            ->call('loadRegistration')
            ->call('loadRegistration')
            ->assertSee('04.03.2027')
            ->assertSee('Natro');

        expect($lookup->asked)->toBe(['logi.com.tr']);
    });

    it('says "Okunamadı" when nothing could be read and nothing is stored', function () {
        fakeRegistrations([]);

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
            ->call('loadRegistration')
            ->assertSee(__('domain_check.unreadable'))
            ->assertDontSee(__('domain_check.stored_answer'));
    });

    it('falls back to the stored answer and says so', function () {
        fakeRegistrations([]);
        $item = domainServiceItem('logi.com.tr');
        $item->forceFill(['registration_expires_at' => '2027-03-04', 'registrar' => 'Natro'])->saveQuietly();

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
            ->call('loadRegistration')
            ->assertSee('04.03.2027')
            ->assertSee(__('domain_check.stored_answer'));
    });

    it('reads again on "Yeniden sorgula" only once the tab was read', function () {
        $lookup = fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);

        $component = Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])->call('refresh');
        expect($lookup->asked)->toBe([]);

        $component->call('loadRegistration')->call('refresh');
        expect($lookup->asked)->toHaveCount(2);
    });
});

describe('nameservers', function () {
    it('lists the registry name servers and finds no difference when DNS answers the same', function () {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null, ['ns1.logi.net', 'ns2.logi.net']]]);
        fakeLiveNameservers(['ns2.logi.net', 'ns1.logi.net']);

        $result = app(CheckDomainRegistration::class)->handle('logi.com.tr');

        expect($result['nameservers'])->toBe(['ns1.logi.net', 'ns2.logi.net'])->and($result['nameservers_differ'])->toBeFalse();
    });

    it('warns when DNS answers with other name servers', function () {
        $this->actingAs(User::factory()->admin()->create());
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null, ['ns1.logi.net']]]);
        fakeLiveNameservers(['anna.ns.cloudflare.com']);

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
            ->call('loadRegistration')
            ->assertSee('ns1.logi.net')
            ->assertSee(__('domain_check.nameservers_differ'));
    });

    it('never warns when either side is unknown', function (?array $live, array $registered) {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null, $registered]]);
        fakeLiveNameservers($live);

        expect(app(CheckDomainRegistration::class)->handle('logi.com.tr')['nameservers_differ'])->toBeFalse();
    })->with([
        'DNS failed' => [null, ['ns1.logi.net']],
        'DNS has none' => [[], ['ns1.logi.net']],
        'registry lists none' => [['ns1.logi.net'], []],
    ]);

    it('shows no name servers from the stored answer', function () {
        fakeRegistrations([]);
        fakeLiveNameservers(['ns1.logi.net']);
        domainServiceItem('logi.com.tr')->forceFill(['registration_expires_at' => '2027-03-04'])->saveQuietly();

        $result = app(CheckDomainRegistration::class)->handle('logi.com.tr');

        expect($result['stored'])->toBeTrue()->and($result['nameservers'])->toBe([])->and($result['nameservers_differ'])->toBeFalse();
    });
});

describe('mismatch', function () {
    it('reports a service end more than 7 days from the registration end', function (string $serviceEnd, bool $reported) {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        domainServiceItem('logi.com.tr', ['end_date' => $serviceEnd]);

        $mismatches = app(CheckDomainRegistration::class)->handle('logi.com.tr')['mismatches'];

        expect($mismatches !== [])->toBe($reported);
    })->with([
        'same day' => ['2027-03-04', false],
        '7 days earlier' => ['2027-02-25', false],
        '8 days earlier' => ['2027-02-24', true],
        '7 days later' => ['2027-03-11', false],
        '8 days later' => ['2027-03-12', true],
    ]);

    it('names the customer and the difference in days', function () {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        domainServiceItem('logi.com.tr', ['end_date' => '2027-04-01']);

        expect(app(CheckDomainRegistration::class)->handle('logi.com.tr')['mismatches'])
            ->toBe([['customer' => 'Logi', 'service_end' => '2027-04-01', 'days' => 28]]);
    });

    it('compares with domain services only, not with the hosting that uses the domain', function () {
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        domainServiceItem('logi.com.tr', ['end_date' => '2027-12-01'], ServiceType::Hosting);
        domainServiceItem('logi.com.tr', ['end_date' => '2027-12-01', 'status' => CustomerServiceStatus::Cancelled]);

        expect(app(CheckDomainRegistration::class)->handle('logi.com.tr')['mismatches'])->toBe([]);
    });

    it('shows the warning in the window', function () {
        $this->actingAs(User::factory()->admin()->create());
        fakeRegistrations(['logi.com.tr' => ['2027-03-04', null]]);
        domainServiceItem('logi.com.tr', ['end_date' => '2027-04-01']);

        Livewire::test(CheckDomain::class, ['domain' => 'logi.com.tr'])
            ->call('loadRegistration')
            ->assertSee(__('domain_check.mismatch', ['customer' => 'Logi', 'service' => '01.04.2027', 'days' => 28]));
    });

    it('refuses an invalid domain without asking WHOIS', function () {
        $lookup = fakeRegistrations([]);

        expect(app(CheckDomainRegistration::class)->handle('not a host'))->toBeNull()->and($lookup->asked)->toBe([]);
    });
});
