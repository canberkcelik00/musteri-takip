<?php

use App\Enums\Currency;
use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Models\CatalogService;
use App\Models\Package;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('creates a package with its own price and its catalog services', function () {
    $services = CatalogService::factory()->count(3)->create();

    Livewire::test(CreatePackage::class)
        ->fillForm([
            'name' => 'Kurumsal Paket',
            'price' => '4.000',
            'currency' => Currency::Try->value,
            'catalogServices' => [$services[0]->id, $services[1]->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $package = Package::firstOrFail();

    expect($package->name)->toBe('Kurumsal Paket')
        ->and($package->price)->toBe(400000)
        ->and($package->currency)->toBe(Currency::Try)
        ->and($package->catalogServices->pluck('id')->all())->toEqualCanonicalizing([$services[0]->id, $services[1]->id]);
});

it('requires a name, a price and at least two catalog services', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(CreatePackage::class)
        ->fillForm(['name' => null, 'price' => null, 'catalogServices' => []])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'price' => 'required', 'catalogServices' => 'required']);

    Livewire::test(CreatePackage::class)
        ->fillForm(['name' => 'Tek Hizmet', 'price' => '1.000', 'catalogServices' => [$service->id]])
        ->call('create')
        ->assertHasFormErrors(['catalogServices']);

    expect(Package::count())->toBe(0);
});

it('lists only catalog services that are not archived when selecting package contents', function () {
    $active = CatalogService::factory()->create(['name' => 'Aktif Hosting']);
    $archived = CatalogService::factory()->archived()->create(['name' => 'Eski Hosting']);

    Livewire::test(CreatePackage::class)
        ->assertFormFieldExists('catalogServices', function ($field) use ($active, $archived): bool {
            $options = $field->getOptions();

            return array_key_exists($active->id, $options) && ! array_key_exists($archived->id, $options);
        });
});

it('keeps an archived catalog service that is already in the package selectable and labelled', function () {
    $active = CatalogService::factory()->create(['name' => 'Aktif Hosting']);
    $archived = CatalogService::factory()->archived()->create(['name' => 'Eski Hosting']);
    $unrelatedArchived = CatalogService::factory()->archived()->create(['name' => 'Alakasız']);
    $package = Package::factory()->create();
    $package->catalogServices()->attach([$active->id, $archived->id]);

    Livewire::test(EditPackage::class, ['record' => $package->id])
        ->assertFormFieldExists('catalogServices', function ($field) use ($archived, $unrelatedArchived): bool {
            $options = $field->getOptions();

            return ($options[$archived->id] ?? null) === 'Eski Hosting (arşivlendi)'
                && ! array_key_exists($unrelatedArchived->id, $options);
        });
});

it('does not drop an archived catalog service from the package when saving', function () {
    $active = CatalogService::factory()->create();
    $archived = CatalogService::factory()->archived()->create();
    $package = Package::factory()->create(['price' => 100000]);
    $package->catalogServices()->attach([$active->id, $archived->id]);

    Livewire::test(EditPackage::class, ['record' => $package->id])
        ->assertFormSet(['catalogServices' => [$active->id, $archived->id]])
        ->fillForm(['name' => 'Yeni Ad', 'price' => '2.500,50'])
        ->call('save')
        ->assertHasNoFormErrors();

    $package->refresh();

    expect($package->name)->toBe('Yeni Ad')
        ->and($package->price)->toBe(250050)
        ->and($package->catalogServices->pluck('id')->all())->toEqualCanonicalizing([$active->id, $archived->id]);
});

it('lists packages with their contents and price', function () {
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000]);
    $package->catalogServices()->attach([
        CatalogService::factory()->create(['name' => 'Linux Hosting'])->id,
        CatalogService::factory()->create(['name' => 'Bakım'])->id,
    ]);

    Livewire::test(ListPackages::class)
        ->assertCanSeeTableRecords([$package])
        ->assertSee('Linux Hosting')
        ->assertSee('Bakım')
        ->assertSee('4.000 TL');
});

it('searches the list by name', function () {
    $kurumsal = Package::factory()->create(['name' => 'Kurumsal Paket']);
    $baslangic = Package::factory()->create(['name' => 'Başlangıç Paketi']);

    Livewire::test(ListPackages::class)
        ->searchTable('Kurumsal')
        ->assertCanSeeTableRecords([$kurumsal])
        ->assertCanNotSeeTableRecords([$baslangic]);
});

it('hides archived packages from the list by default and shows them with the archive filter', function () {
    $active = Package::factory()->create();
    $archived = Package::factory()->archived()->create();

    Livewire::test(ListPackages::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived])
        ->filterTable('archived', true)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active])
        ->filterTable('archived', null)
        ->assertCanSeeTableRecords([$active, $archived]);
});

it('archives and unarchives a package from the list', function () {
    $package = Package::factory()->create();

    Livewire::test(ListPackages::class)
        ->callAction(TestAction::make('archive')->table($package));

    expect($package->refresh()->isArchived())->toBeTrue();

    Livewire::test(ListPackages::class)
        ->filterTable('archived', true)
        ->callAction(TestAction::make('unarchive')->table($package));

    expect($package->refresh()->isArchived())->toBeFalse();
});

it('archives and unarchives a package from its edit page', function () {
    $package = Package::factory()->create();

    Livewire::test(EditPackage::class, ['record' => $package->id])
        ->assertActionVisible('archive')
        ->assertActionHidden('unarchive')
        ->callAction('archive');

    expect($package->refresh()->isArchived())->toBeTrue();

    Livewire::test(EditPackage::class, ['record' => $package->id])
        ->assertActionHidden('archive')
        ->callAction('unarchive');

    expect($package->refresh()->isArchived())->toBeFalse();
});

it('offers no delete action', function () {
    $package = Package::factory()->create();

    Livewire::test(ListPackages::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table($package))
        ->assertTableBulkActionDoesNotExist('delete');

    Livewire::test(EditPackage::class, ['record' => $package->id])
        ->assertActionDoesNotExist('delete');
});
