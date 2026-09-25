<?php

use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Kurumsal Paket = hosting + domain + maintenance, 4.000 TL.
 *
 * @return array{0: Package, 1: CatalogService, 2: CatalogService, 3: CatalogService}
 */
function corporatePackage(): array
{
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create(['name' => 'Linux Hosting 1000 MB']);
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create(['name' => '.com.tr Alan Adı']);
    $maintenance = CatalogService::factory()->ofType(ServiceType::Maintenance)->create(['name' => 'Bakım']);

    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000, 'currency' => Currency::Try]);
    $package->catalogServices()->attach([$hosting->id, $domain->id, $maintenance->id]);

    return [$package, $hosting, $domain, $maintenance];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function assignService(Customer $customer, CatalogService|Package $sold, array $attributes = []): CustomerService
{
    return app(AssignCustomerService::class)->handle($customer, $sold, ['start_date' => '2026-03-01', ...$attributes]);
}

function recordCreditBalance(Customer $customer, int $amount, Currency $currency = Currency::Try): void
{
    app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => $currency,
        'paid_on' => '2026-02-20',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-03-01');
});

it('assigns a single catalog service with its first period charge', function () {
    $customer = Customer::factory()->create();
    $catalogService = CatalogService::factory()->ofType(ServiceType::Domain)->create([
        'name' => '.com.tr Alan Adı',
        'price' => 45000,
        'currency' => Currency::Usd,
    ]);

    $customerService = assignService($customer, $catalogService);

    expect($customerService->customer_id)->toBe($customer->id)
        ->and($customerService->catalog_service_id)->toBe($catalogService->id)
        ->and($customerService->package_id)->toBeNull()
        ->and($customerService->name)->toBe('.com.tr Alan Adı')
        ->and($customerService->price)->toBe(45000)
        ->and($customerService->currency)->toBe(Currency::Usd)
        ->and($customerService->start_date->toDateString())->toBe('2026-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01')
        ->and($customerService->is_free)->toBeFalse();

    $items = $customerService->items;
    expect($items)->toHaveCount(1)
        ->and($items->first()->catalog_service_id)->toBe($catalogService->id)
        ->and($items->first()->type)->toBe(ServiceType::Domain)
        ->and($items->first()->name)->toBe('.com.tr Alan Adı');

    $charge = $customerService->charges()->sole();
    expect($charge->customer_id)->toBe($customer->id)
        ->and($charge->description)->toBe('.com.tr Alan Adı 2026–2027')
        ->and($charge->amount)->toBe(45000)
        ->and($charge->currency)->toBe(Currency::Usd)
        ->and($charge->due_date->toDateString())->toBe('2026-03-01')
        ->and($charge->period_start->toDateString())->toBe('2026-03-01')
        ->and($charge->period_end->toDateString())->toBe('2027-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open);
});

it('assigns a package with an item per catalog service and a single charge', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    [$package, $hosting, $domain, $maintenance] = corporatePackage();

    $customerService = assignService($customer, $package);

    expect($customerService->package_id)->toBe($package->id)
        ->and($customerService->catalog_service_id)->toBeNull()
        ->and($customerService->name)->toBe('Kurumsal Paket')
        ->and($customerService->price)->toBe(400000)
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01')
        ->and($customerService->items()->orderBy('id')->pluck('type', 'catalog_service_id')->all())->toBe([
            $hosting->id => ServiceType::Hosting,
            $domain->id => ServiceType::Domain,
            $maintenance->id => ServiceType::Maintenance,
        ]);

    $charge = Charge::query()->sole();
    expect($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->description)->toBe('Kurumsal Paket 2026–2027')
        ->and($charge->amount)->toBe(400000)
        ->and($charge->due_date->toDateString())->toBe('2026-03-01')
        ->and($charge->period_start->toDateString())->toBe('2026-03-01')
        ->and($charge->period_end->toDateString())->toBe('2027-03-01');
});

it('creates no charge for a free service', function () {
    $customer = Customer::factory()->create();

    $customerService = assignService($customer, CatalogService::factory()->create(), ['is_free' => true]);

    expect($customerService->is_free)->toBeTrue()
        ->and($customerService->items()->count())->toBe(1)
        ->and(Charge::query()->exists())->toBeFalse();
});

it('uses the given price and currency instead of the catalog ones', function () {
    $customer = Customer::factory()->create();
    [$package] = corporatePackage();

    $customerService = assignService($customer, $package, ['price' => 350050, 'currency' => Currency::Eur]);

    $charge = $customerService->charges()->sole();
    expect($customerService->price)->toBe(350050)
        ->and($customerService->currency)->toBe(Currency::Eur)
        ->and($charge->amount)->toBe(350050)
        ->and($charge->currency)->toBe(Currency::Eur);
});

it('creates no charge for this period when migrating from Excel', function () {
    $customer = Customer::factory()->create();
    [$package] = corporatePackage();

    $customerService = assignService($customer, $package, ['start_date' => '2025-11-15', 'create_first_charge' => false]);

    expect($customerService->start_date->toDateString())->toBe('2025-11-15')
        ->and($customerService->end_date->toDateString())->toBe('2026-11-15')
        ->and($customerService->items()->count())->toBe(3)
        ->and(Charge::query()->exists())->toBeFalse();
});

it('applies the credit balance to the first charge', function () {
    $customer = Customer::factory()->create();
    recordCreditBalance($customer, 500000);
    [$package] = corporatePackage();

    $charge = assignService($customer, $package)->charges()->sole();

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->remainingAmount())->toBe(0)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});

it('does not apply a credit balance in another currency to the first charge', function () {
    $customer = Customer::factory()->create();
    recordCreditBalance($customer, 500000, Currency::Usd);
    [$package] = corporatePackage();

    $charge = assignService($customer, $package)->charges()->sole();

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->remainingAmount())->toBe(400000)
        ->and($customer->creditBalances())->toEqual(['USD' => new Money(500000, Currency::Usd)]);
});

it('keeps the customer service and its charge when catalog prices change later', function () {
    $customer = Customer::factory()->create();
    [$package, $hosting] = corporatePackage();
    $single = CatalogService::factory()->create(['price' => 100000]);

    $fromPackage = assignService($customer, $package);
    $fromSingle = assignService($customer, $single);

    $package->update(['price' => 500000, 'currency' => Currency::Usd, 'name' => 'Kurumsal Paket Plus']);
    $hosting->update(['name' => 'Linux Hosting 2000 MB']);
    $single->update(['price' => 150000]);

    expect($fromPackage->fresh()->price)->toBe(400000)
        ->and($fromPackage->fresh()->currency)->toBe(Currency::Try)
        ->and($fromPackage->fresh()->name)->toBe('Kurumsal Paket')
        ->and($fromPackage->items()->where('catalog_service_id', $hosting->id)->value('name'))->toBe('Linux Hosting 1000 MB')
        ->and($fromPackage->charges()->sole()->amount)->toBe(400000)
        ->and($fromSingle->fresh()->price)->toBe(100000)
        ->and($fromSingle->charges()->sole()->amount)->toBe(100000);
});

it('ends a period starting on 29 February on 28 February', function () {
    $customerService = assignService(Customer::factory()->create(), CatalogService::factory()->create(), ['start_date' => '2028-02-29']);

    $charge = $customerService->charges()->sole();
    expect($customerService->end_date->toDateString())->toBe('2029-02-28')
        ->and($charge->period_end->toDateString())->toBe('2029-02-28')
        ->and($charge->description)->toEndWith('2028–2029');
});

it('rejects a zero price for a service that is not free', function () {
    $customer = Customer::factory()->create();

    expect(fn () => assignService($customer, CatalogService::factory()->create(), ['price' => 0]))
        ->toThrow(InvalidCustomerService::class, __('customer_services.price_not_positive'));

    expect(CustomerService::query()->exists())->toBeFalse()
        ->and(CustomerServiceItem::query()->exists())->toBeFalse()
        ->and(Charge::query()->exists())->toBeFalse();
});

it('rejects a start date whose period has already ended', function (string $startDate, bool $createFirstCharge) {
    $customer = Customer::factory()->create();
    [$package] = corporatePackage();

    $this->travelTo('2026-09-25');

    expect(fn () => assignService($customer, $package, ['start_date' => $startDate, 'create_first_charge' => $createFirstCharge]))
        ->toThrow(InvalidCustomerService::class, 'Başlangıç tarihi olarak hizmetin şu anki döneminin başlangıcını girin.');

    expect(CustomerService::query()->exists())->toBeFalse()
        ->and(CustomerServiceItem::query()->exists())->toBeFalse()
        ->and(Charge::query()->exists())->toBeFalse();
})->with([
    'period ends today' => ['2025-09-25', true],
    'period ended before today' => ['2024-03-01', true],
    'migrating from Excel' => ['2025-03-01', false],
]);

it('accepts a start date whose period ends tomorrow', function () {
    $this->travelTo('2026-09-25');

    $customerService = assignService(Customer::factory()->create(), CatalogService::factory()->create(), ['start_date' => '2025-09-26']);

    expect($customerService->end_date->toDateString())->toBe('2026-09-26')
        ->and($customerService->charges()->count())->toBe(1);
});

it('accepts a zero price for a free service', function () {
    $customerService = assignService(Customer::factory()->create(), CatalogService::factory()->create(), ['price' => 0, 'is_free' => true]);

    expect($customerService->price)->toBe(0)
        ->and(Charge::query()->exists())->toBeFalse();
});

it('saves the customer details of each package item on assignment', function () {
    [$package, $hosting, $domain, $maintenance] = corporatePackage();

    $customerService = assignService(Customer::factory()->create(), $package, [
        'items' => [
            $hosting->id => ['details' => ['domain' => 'logi.com.tr', 'server' => 'srv1.example.net', 'cpanel_username' => 'logicom']],
            $domain->id => ['details' => ['domain' => 'logi.com.tr']],
        ],
    ]);

    $items = $customerService->items()->get()->keyBy('catalog_service_id');
    expect($items[$hosting->id]->details)->toBe(['domain' => 'logi.com.tr', 'server' => 'srv1.example.net', 'cpanel_username' => 'logicom'])
        ->and($items[$domain->id]->details)->toBe(['domain' => 'logi.com.tr'])
        ->and($items[$maintenance->id]->details)->toBeNull();
});

it('drops detail keys that do not belong to the item type', function () {
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create();

    $customerService = assignService(Customer::factory()->create(), $domain, [
        'items' => [$domain->id => ['details' => ['domain' => 'logi.com.tr', 'cpanel_username' => 'logicom']]],
    ]);

    expect($customerService->items()->sole()->details)->toBe(['domain' => 'logi.com.tr']);
});

it('stores the license key of a license item encrypted', function () {
    $license = CatalogService::factory()->ofType(ServiceType::License)->create();

    $customerService = assignService(Customer::factory()->create(), $license, [
        'items' => [$license->id => ['details' => ['quantity' => '5'], 'license_key' => 'ABCDE-12345-FGHIJ']],
    ]);

    $item = $customerService->items()->sole();
    $stored = DB::table('customer_service_items')->where('id', $item->id)->value('license_key');

    expect($item->license_key)->toBe('ABCDE-12345-FGHIJ')
        ->and($item->details)->toBe(['quantity' => '5'])
        ->and($stored)->not->toContain('ABCDE-12345-FGHIJ')
        ->and(decrypt($stored, false))->toBe('ABCDE-12345-FGHIJ');
});

it('drops a license key given for an item that is not a license', function () {
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();

    $customerService = assignService(Customer::factory()->create(), $hosting, [
        'items' => [$hosting->id => ['license_key' => 'ABCDE-12345-FGHIJ']],
    ]);

    expect($customerService->items()->sole()->license_key)->toBeNull();
});

it('rejects item details for a catalog service that is not sold', function () {
    [$package] = corporatePackage();
    $other = CatalogService::factory()->create();

    expect(fn () => assignService(Customer::factory()->create(), $package, [
        'items' => [$other->id => ['details' => ['domain' => 'logi.com.tr']]],
    ]))->toThrow(InvalidCustomerService::class, __('customer_services.item_not_in_sold_service'));

    expect(CustomerService::query()->exists())->toBeFalse()
        ->and(CustomerServiceItem::query()->exists())->toBeFalse()
        ->and(Charge::query()->exists())->toBeFalse();
});

it('assigns an archived catalog service or package', function () {
    $customer = Customer::factory()->create();
    $catalogService = CatalogService::factory()->archived()->create();
    [$package] = corporatePackage();
    $package->archive();

    assignService($customer, $catalogService, ['create_first_charge' => false]);
    assignService($customer, $package, ['create_first_charge' => false]);

    expect($customer->customerServices()->count())->toBe(2);
});

it('rejects assigning to an archived customer and changes nothing', function () {
    $customer = Customer::factory()->archived()->create();
    $catalogService = CatalogService::factory()->create();

    expect(fn () => assignService($customer, $catalogService))
        ->toThrow(InvalidCustomerService::class, 'Önce müşteriyi arşivden çıkarın.');

    expect(CustomerService::query()->exists())->toBeFalse()
        ->and(CustomerServiceItem::query()->exists())->toBeFalse()
        ->and(Charge::query()->exists())->toBeFalse();
});
