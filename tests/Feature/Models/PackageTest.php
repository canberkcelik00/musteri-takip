<?php

use App\Enums\Currency;
use App\Models\CatalogService;
use App\Models\Package;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('uses Turkish lira by default', function () {
    $package = Package::create(['name' => 'Kurumsal Paket', 'price' => 400000])->fresh();

    expect($package->currency)->toBe(Currency::Try)
        ->and($package->price)->toBe(400000);
});

it('does not add the same catalog service to a package twice', function () {
    $catalogService = CatalogService::factory()->create();
    $package = Package::factory()->hasAttached($catalogService)->create();

    expect(fn () => $package->catalogServices()->attach($catalogService))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('adds the same catalog service to different packages', function () {
    $catalogService = CatalogService::factory()->create();
    Package::factory()->count(2)->hasAttached($catalogService)->create();

    expect($catalogService->packages()->count())->toBe(2);
});

it('lists archived and not archived packages separately', function () {
    $active = Package::factory()->create();
    $archived = Package::factory()->archived()->create();

    expect(Package::notArchived()->pluck('id')->all())->toBe([$active->id])
        ->and(Package::onlyArchived()->pluck('id')->all())->toBe([$archived->id]);
});

it('keeps the catalog services of an archived package', function () {
    $package = Package::factory()->withCatalogServices(3)->create();
    $catalogServiceIds = $package->catalogServices->modelKeys();

    $package->archive();

    expect($package->fresh()->catalogServices->modelKeys())->toEqualCanonicalizing($catalogServiceIds);
});

it('deletes the package items but not the catalog services of a deleted package', function () {
    $package = Package::factory()->withCatalogServices(2)->create();

    $package->delete();

    expect(DB::table('package_items')->count())->toBe(0)
        ->and(CatalogService::count())->toBe(2);
});
