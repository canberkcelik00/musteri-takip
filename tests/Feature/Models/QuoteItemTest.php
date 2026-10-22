<?php

use App\Enums\Currency;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\QuoteItem;
use Illuminate\Database\QueryException;

it('copies name, price and currency from the quoted catalog service', function () {
    $catalogService = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000, 'currency' => Currency::Usd]);

    $item = QuoteItem::factory()->create(['catalog_service_id' => $catalogService->id])->fresh();

    expect($item->name)->toBe('Linux Hosting 1000 MB')
        ->and($item->price)->toBe(150000)
        ->and($item->currency)->toBe(Currency::Usd)
        ->and($item->is_free)->toBeFalse()
        ->and($item->catalogService->is($catalogService))->toBeTrue()
        ->and($item->package)->toBeNull();
});

it('quotes a package', function () {
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

    $item = QuoteItem::factory()->forPackage()->create(['package_id' => $package->id]);

    expect($item->name)->toBe('Kurumsal Paket')
        ->and($item->price)->toBe(400000)
        ->and($item->package->is($package))->toBeTrue()
        ->and($item->catalogService)->toBeNull();
});

it('has exactly one of catalog service and package', function () {
    expect(fn () => QuoteItem::factory()->create([
        'catalog_service_id' => null,
        'package_id' => null,
        'name' => 'Boş',
        'price' => 100,
        'currency' => Currency::Try,
    ]))->toThrow(QueryException::class)
        ->and(fn () => QuoteItem::factory()->create([
            'catalog_service_id' => CatalogService::factory()->create()->id,
            'package_id' => Package::factory()->create()->id,
        ]))->toThrow(QueryException::class);
});

it('can be free', function () {
    $item = QuoteItem::factory()->free()->create()->fresh();

    expect($item->is_free)->toBeTrue()
        ->and($item->price)->toBe(0);
});

it('links to the customer service created from it, once', function () {
    $item = QuoteItem::factory()->create();
    $customerService = CustomerService::factory()->create(['quote_item_id' => $item->id]);

    expect($item->customerService->is($customerService))->toBeTrue()
        ->and($customerService->quoteItem->is($item))->toBeTrue()
        ->and(fn () => CustomerService::factory()->create(['quote_item_id' => $item->id]))
        ->toThrow(QueryException::class);
});
