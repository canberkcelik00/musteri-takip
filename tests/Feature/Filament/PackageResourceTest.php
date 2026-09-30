<?php

use App\Enums\Currency;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function createPackagePanel()
{
    return Livewire::test(ListPackages::class)->mountAction('create');
}

function editPackagePanel(Package $package): TestAction
{
    return TestAction::make('edit')->table($package);
}

it('creates a package with its own price and its catalog services', function () {
    $services = CatalogService::factory()->count(3)->create();

    createPackagePanel()
        ->fillForm([
            'name' => 'Kurumsal Paket',
            'price' => '4.000',
            'currency' => Currency::Try->value,
            'catalogServices' => [$services[0]->id, $services[1]->id],
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $package = Package::firstOrFail();

    expect($package->name)->toBe('Kurumsal Paket')
        ->and($package->price)->toBe(400000)
        ->and($package->currency)->toBe(Currency::Try)
        ->and($package->catalogServices->pluck('id')->all())->toEqualCanonicalizing([$services[0]->id, $services[1]->id]);
});

it('requires a name, a price and at least two catalog services', function () {
    $service = CatalogService::factory()->create();

    createPackagePanel()
        ->fillForm(['name' => null, 'price' => null, 'catalogServices' => []])
        ->callMountedAction()
        ->assertHasFormErrors(['name' => 'required', 'price' => 'required', 'catalogServices' => 'required']);

    createPackagePanel()
        ->fillForm(['name' => 'Tek Hizmet', 'price' => '1.000', 'catalogServices' => [$service->id]])
        ->callMountedAction()
        ->assertHasFormErrors(['catalogServices']);

    expect(Package::count())->toBe(0);
});

it('lists only catalog services that are not archived when selecting package contents', function () {
    $active = CatalogService::factory()->create(['name' => 'Aktif Hosting']);
    $archived = CatalogService::factory()->archived()->create(['name' => 'Eski Hosting']);

    createPackagePanel()
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

    Livewire::test(ListPackages::class)
        ->mountAction(editPackagePanel($package))
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

    Livewire::test(ListPackages::class)
        ->mountAction(editPackagePanel($package))
        ->assertSchemaStateSet(['catalogServices' => [$active->id, $archived->id]])
        ->fillForm(['name' => 'Yeni Ad', 'price' => '2.500,50'])
        ->callMountedAction()
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

it('archives and unarchives a package from its edit panel', function () {
    $package = Package::factory()->create();

    $list = Livewire::test(ListPackages::class);

    expect(actionOffered($list, sectionAction(editPackagePanel($package), 'archive')))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editPackagePanel($package), 'unarchive')))->toBeFalse();

    $list->callAction(sectionAction(editPackagePanel($package), 'archive'));

    expect($package->refresh()->isArchived())->toBeTrue();

    Livewire::test(ListPackages::class)
        ->filterTable('archived', true)
        ->callAction(sectionAction(editPackagePanel($package), 'unarchive'));

    expect($package->refresh()->isArchived())->toBeFalse();
});

it('offers delete next to archive for an unsold package and deletes it', function () {
    $package = Package::factory()->withCatalogServices()->create();

    $list = Livewire::test(ListPackages::class);

    expect(actionOffered($list, sectionAction(editPackagePanel($package), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editPackagePanel($package), 'archive')))->toBeTrue();

    $list->callAction(sectionAction(editPackagePanel($package), 'delete', danger: true));

    expect(Package::query()->find($package->id))->toBeNull()
        ->and(CatalogService::query()->count())->toBe(3);
});

it('offers only archive for a package sold to a customer', function () {
    $package = Package::factory()->withCatalogServices()->create();
    CustomerService::factory()->forPackage($package)->create();

    $list = Livewire::test(ListPackages::class);

    expect(actionOffered($list, sectionAction(editPackagePanel($package), 'delete', danger: true)))->toBeFalse()
        ->and(actionOffered($list, sectionAction(editPackagePanel($package), 'archive')))->toBeTrue();
});

it('offers delete and unarchive for an archived package that is not in use', function () {
    $package = Package::factory()->withCatalogServices()->archived()->create();

    $list = Livewire::test(ListPackages::class)->filterTable('archived', true);

    expect(actionOffered($list, sectionAction(editPackagePanel($package), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editPackagePanel($package), 'unarchive')))->toBeTrue();
});
