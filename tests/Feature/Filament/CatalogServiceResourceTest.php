<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\CatalogServices\Pages\CreateCatalogService;
use App\Filament\Resources\CatalogServices\Pages\EditCatalogService;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
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

it('shows the hosting fields when hosting is selected and the domain fields when domain is selected', function () {
    Livewire::test(CreateCatalogService::class)
        ->fillForm(['type' => ServiceType::Hosting->value])
        ->assertFormFieldExists('details.disk_space')
        ->assertFormFieldExists('details.monthly_traffic')
        ->assertFormFieldExists('details.sites')
        ->assertFormFieldDoesNotExist('details.registrar')
        ->assertFormFieldDoesNotExist('details.extension')
        ->fillForm(['type' => ServiceType::Domain->value])
        ->assertFormFieldExists('details.registrar')
        ->assertFormFieldExists('details.extension')
        ->assertFormFieldDoesNotExist('details.disk_space');
});

it('shows no type specific fields until a type is selected', function () {
    Livewire::test(CreateCatalogService::class)
        ->assertFormFieldDoesNotExist('details.disk_space')
        ->assertFormFieldDoesNotExist('details.registrar');
});

it('labels the type specific fields in Turkish', function () {
    Livewire::test(CreateCatalogService::class)
        ->fillForm(['type' => ServiceType::Hosting->value])
        ->assertSee('Disk alanı')
        ->assertSee('Aylık trafik');
});

it('creates a catalog service with its price and type specific details', function () {
    Livewire::test(CreateCatalogService::class)
        ->fillForm([
            'type' => ServiceType::Hosting->value,
            'name' => 'Linux Hosting 1000 MB',
            'price' => '4.000,50',
            'currency' => Currency::Try->value,
            'details' => ['disk_space' => '1000 MB', 'sites' => '5'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $service = CatalogService::firstOrFail();

    expect($service->type)->toBe(ServiceType::Hosting)
        ->and($service->name)->toBe('Linux Hosting 1000 MB')
        ->and($service->price)->toBe(400050)
        ->and($service->currency)->toBe(Currency::Try)
        ->and($service->details)->toMatchArray(['disk_space' => '1000 MB', 'sites' => '5'])
        ->and($service->archived_at)->toBeNull();
});

it('requires a type, a name and a price', function () {
    Livewire::test(CreateCatalogService::class)
        ->fillForm(['type' => null, 'name' => null, 'price' => null])
        ->call('create')
        ->assertHasFormErrors(['type' => 'required', 'name' => 'required', 'price' => 'required']);

    expect(CatalogService::count())->toBe(0);
});

it('rejects an amount that is not in the Turkish format', function () {
    Livewire::test(CreateCatalogService::class)
        ->fillForm(['type' => ServiceType::Ssl->value, 'name' => 'SSL', 'price' => '4,000.50'])
        ->call('create')
        ->assertHasFormErrors(['price']);
});

it('fills the edit form from the record', function () {
    $service = CatalogService::factory()->ofType(ServiceType::Domain)->create([
        'price' => 40050,
        'details' => ['registrar' => 'Natro', 'extension' => '.com.tr'],
    ]);

    Livewire::test(EditCatalogService::class, ['record' => $service->id])
        ->assertFormSet(['price' => '400,50', 'details.registrar' => 'Natro', 'details.extension' => '.com.tr']);
});

it('drops the details of the previous type when the type is changed', function () {
    $service = CatalogService::factory()->ofType(ServiceType::Hosting)->create([
        'details' => ['disk_space' => '1000 MB'],
    ]);

    Livewire::test(EditCatalogService::class, ['record' => $service->id])
        ->fillForm(['type' => ServiceType::Domain->value, 'details' => ['registrar' => 'Natro']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($service->refresh()->type)->toBe(ServiceType::Domain)
        ->and($service->details)->toBe(['registrar' => 'Natro']);
});

it('hides archived catalog services from the list by default', function () {
    $active = CatalogService::factory()->create();
    $archived = CatalogService::factory()->archived()->create();

    Livewire::test(ListCatalogServices::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('shows archived catalog services with the archive filter', function () {
    $active = CatalogService::factory()->create();
    $archived = CatalogService::factory()->archived()->create();

    Livewire::test(ListCatalogServices::class)
        ->filterTable('archived', true)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active])
        ->filterTable('archived', null)
        ->assertCanSeeTableRecords([$active, $archived]);
});

it('filters the list by type', function () {
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create();

    Livewire::test(ListCatalogServices::class)
        ->filterTable('type', ServiceType::Domain->value)
        ->assertCanSeeTableRecords([$domain])
        ->assertCanNotSeeTableRecords([$hosting]);
});

it('searches the list by name', function () {
    $linux = CatalogService::factory()->create(['name' => 'Linux Hosting']);
    $other = CatalogService::factory()->create(['name' => 'Kurumsal SSL']);

    Livewire::test(ListCatalogServices::class)
        ->searchTable('Linux')
        ->assertCanSeeTableRecords([$linux])
        ->assertCanNotSeeTableRecords([$other]);
});

it('shows the price in Turkish format on the list', function () {
    CatalogService::factory()->create(['price' => 400050, 'currency' => Currency::Try]);

    Livewire::test(ListCatalogServices::class)->assertSee('4.000,50 TL');
});

it('shows the type as plain text, not a badge', function () {
    $service = CatalogService::factory()->ofType(ServiceType::Hosting)->create();

    $html = Livewire::test(ListCatalogServices::class)
        ->assertTableColumnFormattedStateSet('type', ServiceType::Hosting->getLabel(), record: $service)
        ->html();

    // The label appears as plain cell text, not inside a badge span (docs/UI.md §6:
    // color is reserved for the four fixed status meanings, not the service type).
    expect($html)->not->toMatch('/fi-badge[^>]*>\s*'.preg_quote(ServiceType::Hosting->getLabel(), '/').'/');
});

it('archives and unarchives a catalog service from the list', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(ListCatalogServices::class)
        ->assertActionVisible(TestAction::make('archive')->table($service))
        ->assertActionHidden(TestAction::make('unarchive')->table($service))
        ->callAction(TestAction::make('archive')->table($service));

    expect($service->refresh()->isArchived())->toBeTrue();

    Livewire::test(ListCatalogServices::class)
        ->filterTable('archived', true)
        ->assertActionHidden(TestAction::make('archive')->table($service))
        ->callAction(TestAction::make('unarchive')->table($service));

    expect($service->refresh()->isArchived())->toBeFalse();
});

it('archives and unarchives a catalog service from its edit page', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(EditCatalogService::class, ['record' => $service->id])
        ->assertActionVisible('archive')
        ->assertActionHidden('unarchive')
        ->callAction('archive');

    expect($service->refresh()->isArchived())->toBeTrue();

    Livewire::test(EditCatalogService::class, ['record' => $service->id])
        ->assertActionHidden('archive')
        ->callAction('unarchive');

    expect($service->refresh()->isArchived())->toBeFalse();
});

it('offers delete next to archive for an unused catalog service and deletes it', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(ListCatalogServices::class)
        ->assertActionVisible(TestAction::make('delete')->table($service))
        ->assertActionVisible(TestAction::make('archive')->table($service))
        ->callAction(TestAction::make('delete')->table($service));

    expect(CatalogService::query()->find($service->id))->toBeNull();
});

it('offers only archive for a catalog service that is in a package or was sold', function () {
    $inPackage = CatalogService::factory()->create();
    Package::factory()->create()->catalogServices()->attach($inPackage);
    $sold = CatalogService::factory()->create();
    CustomerService::factory()->forCatalogService($sold)->create();

    Livewire::test(ListCatalogServices::class)
        ->assertActionHidden(TestAction::make('delete')->table($inPackage))
        ->assertActionVisible(TestAction::make('archive')->table($inPackage))
        ->assertActionHidden(TestAction::make('delete')->table($sold))
        ->assertActionVisible(TestAction::make('archive')->table($sold));

    Livewire::test(EditCatalogService::class, ['record' => $sold->id])
        ->assertActionHidden('delete')
        ->assertActionVisible('archive');
});

it('deletes an unused catalog service from its edit page and returns to the list', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(EditCatalogService::class, ['record' => $service->id])
        ->assertActionVisible('delete')
        ->callAction('delete')
        ->assertRedirect(CatalogServiceResource::getUrl('index'));

    expect(CatalogService::query()->find($service->id))->toBeNull();
});

it('offers delete for an archived catalog service that is not in use', function () {
    $service = CatalogService::factory()->create();
    $service->archive();

    Livewire::test(ListCatalogServices::class)
        ->filterTable('archived', true)
        ->assertActionVisible(TestAction::make('delete')->table($service))
        ->assertActionVisible(TestAction::make('unarchive')->table($service));
});
