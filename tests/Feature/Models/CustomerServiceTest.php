<?php

use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use Illuminate\Database\QueryException;

/**
 * @param  array<string, mixed>  $attributes
 */
function createCustomerService(array $attributes = []): CustomerService
{
    return Customer::factory()->create()->customerServices()->create([
        'name' => 'Linux Hosting 1000 MB',
        'price' => 150000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

it('casts price, currency, dates, free flag and status', function () {
    $catalogService = CatalogService::factory()->create();

    $customerService = createCustomerService(['catalog_service_id' => $catalogService->id])->fresh();

    expect($customerService->price)->toBe(150000)
        ->and($customerService->currency)->toBe(Currency::Try)
        ->and($customerService->period_months)->toBe(12)
        ->and($customerService->start_date->format('d.m.Y'))->toBe('01.03.2026')
        ->and($customerService->end_date->format('d.m.Y'))->toBe('01.03.2027')
        ->and($customerService->is_free)->toBeFalse()
        ->and($customerService->status)->toBe(CustomerServiceStatus::Active)
        ->and($customerService->cancelled_on)->toBeNull();
});

it('is saved with only a catalog service or only a package', function () {
    $catalogService = CatalogService::factory()->create();
    $package = Package::factory()->create();

    $single = createCustomerService(['catalog_service_id' => $catalogService->id]);
    $packaged = createCustomerService(['package_id' => $package->id]);

    expect($single->catalogService->is($catalogService))->toBeTrue()
        ->and($single->package)->toBeNull()
        ->and($packaged->package->is($package))->toBeTrue()
        ->and($packaged->catalogService)->toBeNull();
});

it('is not saved without a catalog service or a package', function () {
    expect(fn () => createCustomerService())->toThrow(QueryException::class)
        ->and(CustomerService::count())->toBe(0);
});

it('is not saved with both a catalog service and a package', function () {
    $attributes = [
        'catalog_service_id' => CatalogService::factory()->create()->id,
        'package_id' => Package::factory()->create()->id,
    ];

    expect(fn () => createCustomerService($attributes))->toThrow(QueryException::class)
        ->and(CustomerService::count())->toBe(0);
});

it('cannot be updated to have both a catalog service and a package', function () {
    $customerService = CustomerService::factory()->create();

    expect(fn () => $customerService->update(['package_id' => Package::factory()->create()->id]))
        ->toThrow(QueryException::class);
});

it('is not saved unless the end date is after the start date', function (string $endDate) {
    $attributes = [
        'catalog_service_id' => CatalogService::factory()->create()->id,
        'start_date' => '2026-03-01',
        'end_date' => $endDate,
    ];

    expect(fn () => createCustomerService($attributes))->toThrow(QueryException::class)
        ->and(CustomerService::count())->toBe(0);
})->with([
    'same day' => '2026-03-01',
    'before start' => '2026-02-28',
]);

it('ends on 28 February when the period starts on 29 February', function () {
    $customerService = CustomerService::factory()->create(['start_date' => '2028-02-29']);

    expect($customerService->fresh()->end_date->format('d.m.Y'))->toBe('28.02.2029');
});

it('creates one item for a single catalog service', function () {
    $catalogService = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000]);

    $customerService = CustomerService::factory()->forCatalogService($catalogService)->create();

    expect($customerService->name)->toBe('Linux Hosting 1000 MB')
        ->and($customerService->price)->toBe(150000)
        ->and($customerService->items)->toHaveCount(1)
        ->and($customerService->items->first()->catalog_service_id)->toBe($catalogService->id)
        ->and($customerService->items->first()->type)->toBe($catalogService->type)
        ->and($customerService->items->first()->name)->toBe('Linux Hosting 1000 MB');
});

it('creates one item for each catalog service of a package', function () {
    $package = Package::factory()->withCatalogServices(3)->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

    $customerService = CustomerService::factory()->forPackage($package)->create();

    expect($customerService->catalog_service_id)->toBeNull()
        ->and($customerService->name)->toBe('Kurumsal Paket')
        ->and($customerService->price)->toBe(400000)
        ->and($customerService->items->pluck('catalog_service_id')->sort()->values()->all())
        ->toBe($package->catalogServices->modelKeys());
});

it('has free and cancelled factory states', function () {
    $free = CustomerService::factory()->free()->create();
    $cancelled = CustomerService::factory()->cancelled()->create();

    expect($free->is_free)->toBeTrue()
        ->and($free->status)->toBe(CustomerServiceStatus::Active)
        ->and($cancelled->fresh()->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($cancelled->fresh()->cancelled_on?->isToday())->toBeTrue()
        ->and($cancelled->cancel_note)->not->toBeNull();
});

it('does not change when the catalog service price changes', function () {
    $catalogService = CatalogService::factory()->create(['price' => 100000]);
    $customerService = CustomerService::factory()->forCatalogService($catalogService)->create();

    $catalogService->update(['price' => 200000]);

    expect($customerService->fresh()->price)->toBe(100000);
});

it('lists the customer services of a customer', function () {
    $customerService = CustomerService::factory()->create();

    expect($customerService->customer->customerServices->modelKeys())->toBe([$customerService->id]);
});

it('does not let a customer, catalog service or package with customer services be deleted', function () {
    $single = CustomerService::factory()->create();
    $packaged = CustomerService::factory()->forPackage()->create();

    expect(fn () => $single->customer->delete())->toThrow(QueryException::class)
        ->and(fn () => $single->catalogService->delete())->toThrow(QueryException::class)
        ->and(fn () => $packaged->package->delete())->toThrow(QueryException::class);
});

it('deletes its items with it', function () {
    $customerService = CustomerService::factory()->forPackage()->create();

    $customerService->delete();

    expect($customerService->items()->count())->toBe(0);
});
