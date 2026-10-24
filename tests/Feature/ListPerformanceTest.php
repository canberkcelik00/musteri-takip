<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Enums\SettingKey;
use App\Livewire\Pages\Catalog\Packages as CatalogPackages;
use App\Livewire\Pages\Catalog\Services as CatalogServices;
use App\Livewire\Pages\Charges\Index as ChargesIndex;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Customers\Show as CustomersShow;
use App\Livewire\Pages\Payments\Index as PaymentsIndex;
use App\Livewire\Pages\Settings\PaymentMethods;
use App\Livewire\Pages\Today;
use App\Livewire\Topbar\Search;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Server;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\UsageScenario;

/**
 * G-5.7's speed check: every list and "Bugün" must run the same number of queries with a few
 * hundred customers as with a handful, i.e. nothing is queried per row. The small data set
 * holds every relation and every charge state at least once (Laravel skips an eager load for an
 * empty collection, which would make the small count look cheaper than it is), and both
 * measurements use the lists' own page size (10), so a page of the big set is full and the
 * small one is not.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * Catalog (one archived, one unused), packages, and the customers $from … $to - 1. Customer i
 * takes variant i % 5, so five customers hold every variant:
 * 0 paid charge + unallocated payment (credit balance); 1 overdue charge; 2 partly paid charge;
 * 3 free service ending soon + cancelled service with a cancelled charge; 4 package service +
 * USD charge due soon. Customer 0 is archived every 50th time.
 *
 * @return array{catalog: CatalogService, package: Package}
 */
function perfSeed(int $from, int $to, ?array $shared = null): array
{
    if ($shared === null) {
        $catalog = CatalogService::factory()->ofType(ServiceType::Hosting)->create(['name' => 'Linux Hosting']);
        CatalogService::factory()->ofType(ServiceType::Domain)->create(['name' => 'Alan adı']);
        CatalogService::factory()->ofType(ServiceType::Ssl)->archived()->create(['name' => 'Eski SSL']);
        $package = Package::factory()->withCatalogServices()->create(['name' => 'Kurumsal Paket']);
        Package::factory()->archived()->create(['name' => 'Eski Paket']);
        $shared = ['catalog' => $catalog, 'package' => $package];
    }

    $methodId = PaymentMethod::query()->value('id');

    for ($i = $from; $i < $to; $i++) {
        $customer = Customer::factory()->create(['short_name' => sprintf('Firma %03d', $i), 'legal_title' => "Firma {$i} Ltd. Şti."]);
        Contact::factory()->for($customer)->primary()->create();
        Contact::factory()->for($customer)->create();

        $service = CustomerService::factory()->for($customer)->forCatalogService($shared['catalog'])->create([
            'start_date' => '2026-03-01',
            'end_date' => '2027-03-01',
        ]);
        $service->items()->first()->update(['details' => ['domain' => sprintf('firma%03d.com.tr', $i)]]);

        $payment = fn (int $amount): Payment => Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => $amount,
            'paid_on' => '2026-06-01',
            'payment_method_id' => $methodId,
        ]);
        $allocate = fn (Charge $charge, Payment $payment, int $amount) => PaymentAllocation::factory()->create([
            'charge_id' => $charge->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
        ]);

        match ($i % 5) {
            0 => (function () use ($service, $payment, $allocate): void {
                $charge = Charge::factory()->forCustomerService($service)->paid()->create();
                $allocate($charge, $payment($charge->amount + 50000), $charge->amount);
            })(),
            1 => Charge::factory()->forCustomerService($service)->create(['due_date' => '2026-05-01']),
            2 => (function () use ($service, $payment, $allocate): void {
                $charge = Charge::factory()->forCustomerService($service)->create(['amount' => 100000, 'due_date' => '2026-06-20']);
                $allocate($charge, $payment(40000), 40000);
            })(),
            3 => (function () use ($customer): void {
                CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-07-05', 'end_date' => '2026-07-05']);
                $cancelled = CustomerService::factory()->for($customer)->cancelled()->create(['start_date' => '2025-01-01', 'end_date' => '2026-01-01']);
                Charge::factory()->forCustomerService($cancelled)->cancelled()->create();
            })(),
            default => (function () use ($customer, $shared): void {
                $packaged = CustomerService::factory()->for($customer)->forPackage($shared['package'])->create(['start_date' => '2026-06-10', 'end_date' => '2027-06-10']);
                Charge::factory()->forCustomerService($packaged)->create(['due_date' => '2026-06-10']);
                Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'amount' => 5000, 'due_date' => '2026-06-18']);
            })(),
        };

        if ($i % 50 === 0) {
            $customer->archive();
        }
    }

    return $shared;
}

/**
 * Number of queries the callback runs after one warm-up run (caches such as settings and
 * translations are filled by the first run, not by the screen's rows).
 *
 * @param  Closure(): mixed  $screen
 * @return array{queries: int, rows: ?int}
 */
function perfMeasure(Closure $screen): array
{
    $screen();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $rows = $screen();
    DB::disableQueryLog();

    return ['queries' => count(DB::getQueryLog()), 'rows' => is_int($rows) ? $rows : null];
}

/**
 * @return array<string, array{Closure(): mixed, bool}> Screen => [run it (returns the rendered row count of a
 *                                                      paginated list), whether its row count grows with the data]
 */
function perfScreens(Customer $card): array
{
    $rows = fn (string $page, string $variable, array $query = []): Closure => fn (): int => Livewire::withQueryParams($query)
        ->test($page)
        ->assertOk()
        ->viewData($variable)
        ->count();

    return [
        'Bugün' => [fn () => Livewire::test(Today::class)->assertOk(), false],
        'Bugün: tüm sayfa' => [fn () => test()->get(route('app.today'))->assertOk(), false],
        'Müşteriler' => [$rows(CustomersIndex::class, 'customers'), true],
        'Müşteri kartı' => [fn () => Livewire::test(CustomersShow::class, ['customer' => $card])->assertOk(), false],
        'Açık borçlar' => [$rows(ChargesIndex::class, 'charges'), true],
        'Açık borçlar: gecikmiş' => [$rows(ChargesIndex::class, 'charges', ['onlyOverdue' => true]), true],
        'Ödemeler' => [$rows(PaymentsIndex::class, 'payments'), true],
        'Katalog hizmetleri' => [$rows(CatalogServices::class, 'services'), false],
        'Paketler' => [$rows(CatalogPackages::class, 'packages'), false],
        'Ödeme yöntemleri' => [fn () => Livewire::test(PaymentMethods::class)->assertOk(), false],
        'Ctrl+K arama' => [fn (): int => Livewire::test(Search::class)->set('query', 'Firma')->instance()->results()->count(), true],
    ];
}

it('runs the same number of queries for a few hundred customers as for a handful', function () {
    $shared = perfSeed(0, 5);
    $card = Customer::query()->where('short_name', 'Firma 001')->firstOrFail();

    $small = [];
    foreach (perfScreens($card) as $name => [$screen]) {
        $small[$name] = perfMeasure($screen);
    }

    perfSeed(5, 300, $shared);

    $large = [];
    foreach (perfScreens($card) as $name => [$screen]) {
        $large[$name] = perfMeasure($screen);
    }

    expect(Customer::query()->count())->toBe(300);

    $problems = [];

    foreach (perfScreens($card) as $name => [, $growsWithData]) {
        if ($large[$name]['queries'] !== $small[$name]['queries']) {
            $problems[] = "{$name}: {$small[$name]['queries']} queries with 5 customers, {$large[$name]['queries']} with 300.";
        }

        // A full page against a partly filled one: a per-row query would show up as a difference.
        if ($growsWithData && ! ($small[$name]['rows'] > 0 && $small[$name]['rows'] < $large[$name]['rows'])) {
            $problems[] = "{$name}: the small set must fill part of a page ({$small[$name]['rows']} rows against {$large[$name]['rows']}).";
        }
    }

    expect($problems)->toBe([]);
});

it('reads the usage badges of a customer card with the same queries for one linked account as for many', function () {
    $customer = Customer::factory()->create();
    $linked = function (string $kind, int $count) use ($customer): void {
        $server = $kind === 'whm' ? Server::factory()->whm()->create() : Server::factory()->cpanel()->create();

        for ($i = 0; $i < $count; $i++) {
            $domain = "alan{$count}{$i}{$kind}.com";
            // A WHM account is keyed by its cPanel user; every domain of a cPanel connection shares the connection's user.
            $username = $kind === 'whm' ? "hesap{$count}{$i}" : $server->username;
            UsageScenario::linkedService($server, $kind === 'whm' ? $username : $domain, ['customer_id' => $customer->id], $domain);
            if ($kind === 'whm' || $i === 0) {
                UsageScenario::usage($server, $username);
            }

            UsageScenario::mailbox($server, $username, "info@{$domain}", 900, 1000);
        }
    };

    $queries = [];

    foreach (['whm', 'cpanel'] as $kind) {
        $linked($kind, 1);
        $queries[$kind][1] = perfMeasure(fn () => Livewire::test(CustomersShow::class, ['customer' => $customer])->assertOk())['queries'];
        $linked($kind, 6);
        $queries[$kind][6] = perfMeasure(fn () => Livewire::test(CustomersShow::class, ['customer' => $customer])->assertOk())['queries'];
    }

    expect($queries['whm'][6])->toBe($queries['whm'][1])
        ->and($queries['cpanel'][6])->toBe($queries['cpanel'][1]);
});

it('reads every setting with one query per request', function () {
    $settings = app(Settings::class);
    $settings->renewalLeadDays();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $settings->renewalLeadDays();
    $settings->usageWarningPercent();
    $settings->digestTime();
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toBe([]);

    $settings->set(SettingKey::RenewalLeadDays, '45');

    expect($settings->renewalLeadDays())->toBe(45);

    $settings->set(SettingKey::RenewalLeadDays, null);

    expect($settings->renewalLeadDays())->toBe((int) SettingKey::RenewalLeadDays->default());
});
