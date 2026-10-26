<?php

use App\Enums\ServiceType;
use App\Livewire\Pages\Catalog\Packages;
use App\Livewire\Pages\Catalog\Services;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditCatalogService;
use App\Livewire\Windows\EditPackage;
use App\Models\CatalogService;
use App\Models\Package;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

it('lists catalog services and filters them by type and archive state', function () {
    CatalogService::factory()->ofType(ServiceType::Hosting)->create(['name' => 'Linux Hosting']);
    CatalogService::factory()->ofType(ServiceType::Domain)->create(['name' => 'com.tr Alan Adı']);
    CatalogService::factory()->ofType(ServiceType::Hosting)->archived()->create(['name' => 'Eski Hosting']);

    Livewire::test(Services::class)
        ->assertSee('Linux Hosting')->assertSee('com.tr Alan Adı')->assertDontSee('Eski Hosting')
        ->set('type', ServiceType::Domain->value)->assertSee('com.tr Alan Adı')->assertDontSee('Linux Hosting')
        ->set('type', 'all')->set('archive', Services::ARCHIVE_ARCHIVED)->assertSee('Eski Hosting')->assertDontSee('Linux Hosting')
        ->set('archive', Services::ARCHIVE_ALL)->assertSee('Eski Hosting')->assertSee('Linux Hosting');
});

it('opens the quote window with the catalog service preselected', function () {
    CatalogService::factory()->create(['name' => 'Linux Hosting']);

    $service = CatalogService::query()->firstOrFail();

    Livewire::test(Services::class)->assertSeeHtml("catalog_service:{$service->id}");
});

it('creates and edits a catalog service', function () {
    Livewire::test(EditCatalogService::class)
        ->set('type', ServiceType::Maintenance->value)
        ->set('name', 'Aylık Bakım')
        ->set('price', '1.250,50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('catalog-changed')
        ->assertDispatched('close-window');

    $service = CatalogService::query()->where('name', 'Aylık Bakım')->firstOrFail();
    expect($service->price)->toBe(125050);

    Livewire::test(EditCatalogService::class, ['service' => $service->id])
        ->assertSet('price', '1.250,50')
        ->set('name', 'Yıllık Bakım')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->fresh()->name)->toBe('Yıllık Bakım');
});

it('rejects a catalog service without a name or a valid price', function () {
    Livewire::test(EditCatalogService::class)
        ->set('name', '')
        ->set('price', 'abc')
        ->call('save')
        ->assertHasErrors(['name', 'price']);

    expect(CatalogService::query()->count())->toBe(0);
});

it('archives and deletes a catalog service through the confirm window', function () {
    $service = CatalogService::factory()->create();
    $other = CatalogService::factory()->create();

    Livewire::test(Confirm::class, ['action' => 'archive-catalog-service', 'id' => $service->id])->call('confirm');
    expect($service->fresh()->isArchived())->toBeTrue();

    Livewire::test(Confirm::class, ['action' => 'unarchive-catalog-service', 'id' => $service->id])->call('confirm');
    expect($service->fresh()->isArchived())->toBeFalse();

    Livewire::test(Confirm::class, ['action' => 'delete-catalog-service', 'id' => $other->id])->call('confirm');
    expect(CatalogService::query()->find($other->id))->toBeNull();
});

it('refuses to delete a catalog service that a package uses', function () {
    $services = CatalogService::factory()->count(2)->create();
    $package = Package::factory()->create();
    $package->catalogServices()->attach($services->pluck('id'));

    Livewire::test(Confirm::class, ['action' => 'delete-catalog-service', 'id' => $services->first()->id])
        ->call('confirm')
        ->assertHasErrors('form');

    expect(CatalogService::query()->find($services->first()->id))->not->toBeNull();
});

it('lists packages with their services', function () {
    $services = CatalogService::factory()->count(2)->create();
    $package = Package::factory()->create(['name' => 'Başlangıç Paketi']);
    $package->catalogServices()->attach($services->pluck('id'));
    Package::factory()->archived()->create(['name' => 'Eski Paket']);

    Livewire::test(Packages::class)
        ->assertSee('Başlangıç Paketi')->assertDontSee('Eski Paket')
        ->assertSeeHtml("package:{$package->id}")
        ->set('archive', Packages::ARCHIVE_ALL)->assertSee('Eski Paket');
});

it('creates a package from at least two services and edits it', function () {
    $services = CatalogService::factory()->count(3)->create();

    Livewire::test(EditPackage::class)
        ->set('name', 'Kurumsal Paket')
        ->set('price', '5.000')
        ->set('serviceIds', [(string) $services[0]->id])
        ->call('save')
        ->assertHasErrors('serviceIds');

    Livewire::test(EditPackage::class)
        ->set('name', 'Kurumsal Paket')
        ->set('price', '5.000')
        ->set('serviceIds', [(string) $services[0]->id, (string) $services[1]->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('catalog-changed');

    $package = Package::query()->where('name', 'Kurumsal Paket')->firstOrFail();
    expect($package->price)->toBe(500000)->and($package->catalogServices)->toHaveCount(2);

    Livewire::test(EditPackage::class, ['package' => $package->id])
        ->set('serviceIds', [(string) $services[1]->id, (string) $services[2]->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($package->fresh()->catalogServices->pluck('id')->sort()->values()->all())->toBe([$services[1]->id, $services[2]->id]);
});

it('archives and deletes a package through the confirm window', function () {
    $package = Package::factory()->create();

    Livewire::test(Confirm::class, ['action' => 'archive-package', 'id' => $package->id])->call('confirm');
    expect($package->fresh()->isArchived())->toBeTrue();

    Livewire::test(Confirm::class, ['action' => 'delete-package', 'id' => $package->id])->call('confirm');
    expect(Package::query()->find($package->id))->toBeNull();
});

it('needs a signed-in user for the catalog pages', function () {
    auth()->logout();

    $this->get(route('app.catalog.services'))->assertRedirect();
    $this->get(route('app.catalog.packages'))->assertRedirect();
});
