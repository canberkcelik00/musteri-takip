<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Widgets\SchedulerHealth;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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
    Filament::setCurrentPanel('panel');
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
    $rows = fn (string $page, array $query = []): Closure => fn (): int => Livewire::withQueryParams($query)
        ->test($page)
        ->assertOk()
        ->instance()
        ->getTableRecords()
        ->count();

    return [
        'Bugün: sayılar' => [fn () => Livewire::test(TodayStats::class)->assertOk(), false],
        'Bugün: yapılacaklar' => [fn () => Livewire::test(TodayTasks::class)->assertOk(), false],
        'Bugün: zamanlayıcı uyarısı' => [fn () => Livewire::test(SchedulerHealth::class)->assertOk(), false],
        'Bugün: tüm sayfa' => [fn () => test()->get(Dashboard::getUrl())->assertOk(), false],
        'Müşteriler' => [$rows(ListCustomers::class), true],
        'Müşteri kartı' => [fn () => Livewire::test(ViewCustomer::class, ['record' => $card->id])->assertOk(), false],
        'Açık borçlar' => [$rows(ListCharges::class), true],
        'Açık borçlar: gecikmiş' => [$rows(ListCharges::class, ['filters' => ['only_overdue' => ['isActive' => true]]]), true],
        'Ödemeler' => [$rows(ListPayments::class), true],
        'Katalog hizmetleri' => [$rows(ListCatalogServices::class), false],
        'Paketler' => [$rows(ListPackages::class), false],
        'Ödeme yöntemleri' => [$rows(ManagePaymentMethods::class), false],
        'Ctrl+K arama' => [fn (): int => CustomerResource::getGlobalSearchResults('Firma')->count(), true],
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
