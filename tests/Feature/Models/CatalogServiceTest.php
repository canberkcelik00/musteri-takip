<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Package;
use Illuminate\Database\QueryException;

it('casts type, currency, price and details', function () {
    $catalogService = CatalogService::create([
        'type' => ServiceType::Hosting,
        'name' => 'Linux Hosting 1000 MB',
        'price' => 150000,
        'details' => ['provider' => 'Natro', 'disk_space' => '1000 MB'],
    ])->fresh();

    expect($catalogService->type)->toBe(ServiceType::Hosting)
        ->and($catalogService->currency)->toBe(Currency::Try)
        ->and($catalogService->price)->toBe(150000)
        ->and($catalogService->details)->toEqual(['provider' => 'Natro', 'disk_space' => '1000 MB']);
});

it('drops details keys that do not belong to the type', function () {
    $catalogService = CatalogService::factory()->create([
        'type' => ServiceType::Domain,
        'details' => ['registrar' => 'Nic.tr', 'extension' => '.com.tr', 'disk_space' => '1000 MB', 'foo' => 'bar'],
    ]);

    expect($catalogService->fresh()->details)->toEqual(['registrar' => 'Nic.tr', 'extension' => '.com.tr']);
});

it('drops the details of the previous type when the type changes', function () {
    $catalogService = CatalogService::factory()->create([
        'type' => ServiceType::Hosting,
        'details' => ['provider' => 'Natro', 'disk_space' => '1000 MB'],
    ]);

    $catalogService->update([
        'type' => ServiceType::Domain,
        'details' => [...$catalogService->details, 'registrar' => 'Nic.tr'],
    ]);

    expect($catalogService->fresh()->details)->toEqual(['registrar' => 'Nic.tr']);
});

it('lists archived and not archived catalog services separately', function () {
    $active = CatalogService::factory()->create();
    $archived = CatalogService::factory()->archived()->create();

    expect(CatalogService::notArchived()->pluck('id')->all())->toBe([$active->id])
        ->and(CatalogService::onlyArchived()->pluck('id')->all())->toBe([$archived->id]);
});

it('keeps an archived catalog service in the packages that contain it', function () {
    $catalogService = CatalogService::factory()->create();
    $package = Package::factory()->hasAttached($catalogService)->create();

    $catalogService->archive();

    expect($package->fresh()->catalogServices->modelKeys())->toBe([$catalogService->id])
        ->and($catalogService->fresh()->packages->modelKeys())->toBe([$package->id]);
});

it('does not delete a catalog service that is in a package', function () {
    $catalogService = CatalogService::factory()->create();
    Package::factory()->hasAttached($catalogService)->create();

    expect(fn () => $catalogService->delete())->toThrow(QueryException::class)
        ->and(CatalogService::count())->toBe(1);
});

it('does not change the package price when the catalog service price changes', function () {
    $catalogService = CatalogService::factory()->create(['price' => 100000]);
    $package = Package::factory()->hasAttached($catalogService)->create(['price' => 400000]);

    $catalogService->update(['price' => 200000]);

    expect($package->fresh()->price)->toBe(400000);
});
