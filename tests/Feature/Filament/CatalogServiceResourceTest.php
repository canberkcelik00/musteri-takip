<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function createCatalogPanel()
{
    return Livewire::test(ListCatalogServices::class)->mountAction('create');
}

function editCatalogPanel(CatalogService $service): TestAction
{
    return TestAction::make('edit')->table($service);
}

it('shows the hosting fields when hosting is selected and the domain fields when domain is selected', function () {
    createCatalogPanel()
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
    createCatalogPanel()
        ->assertFormFieldDoesNotExist('details.disk_space')
        ->assertFormFieldDoesNotExist('details.registrar');
});

it('labels the type specific fields in Turkish', function () {
    createCatalogPanel()
        ->fillForm(['type' => ServiceType::Hosting->value])
        ->assertFormFieldExists('details.disk_space', checkFieldUsing: fn (TextInput $field): bool => $field->getLabel() === 'Disk alanı')
        ->assertFormFieldExists('details.monthly_traffic', checkFieldUsing: fn (TextInput $field): bool => $field->getLabel() === 'Aylık trafik');
});

it('creates a catalog service with its price and type specific details', function () {
    createCatalogPanel()
        ->fillForm([
            'type' => ServiceType::Hosting->value,
            'name' => 'Linux Hosting 1000 MB',
            'price' => '4.000,50',
            'currency' => Currency::Try->value,
            'details' => ['disk_space' => '1000 MB', 'sites' => '5'],
        ])
        ->callMountedAction()
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
    createCatalogPanel()
        ->fillForm(['type' => null, 'name' => null, 'price' => null])
        ->callMountedAction()
        ->assertHasFormErrors(['type' => 'required', 'name' => 'required', 'price' => 'required']);

    expect(CatalogService::count())->toBe(0);
});

it('rejects an amount that is not in the Turkish format', function () {
    createCatalogPanel()
        ->fillForm(['type' => ServiceType::Ssl->value, 'name' => 'SSL', 'price' => '4,000.50'])
        ->callMountedAction()
        ->assertHasFormErrors(['price']);
});

it('fills the edit panel from the record', function () {
    $service = CatalogService::factory()->ofType(ServiceType::Domain)->create([
        'price' => 40050,
        'details' => ['registrar' => 'Natro', 'extension' => '.com.tr'],
    ]);

    Livewire::test(ListCatalogServices::class)
        ->mountAction(editCatalogPanel($service))
        ->assertSchemaStateSet(['price' => '400,50', 'details.registrar' => 'Natro', 'details.extension' => '.com.tr']);
});

it('drops the details of the previous type when the type is changed', function () {
    $service = CatalogService::factory()->ofType(ServiceType::Hosting)->create([
        'details' => ['disk_space' => '1000 MB'],
    ]);

    Livewire::test(ListCatalogServices::class)
        ->mountAction(editCatalogPanel($service))
        ->fillForm(['type' => ServiceType::Domain->value, 'details' => ['registrar' => 'Natro']])
        ->callMountedAction()
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

it('archives and unarchives a catalog service from its edit panel', function () {
    $service = CatalogService::factory()->create();

    $list = Livewire::test(ListCatalogServices::class);

    expect(actionOffered($list, sectionAction(editCatalogPanel($service), 'archive')))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editCatalogPanel($service), 'unarchive')))->toBeFalse();

    $list->callAction(sectionAction(editCatalogPanel($service), 'archive'));

    // cancelParentActions(): the edit panel closes together with the nested action.
    expect($service->refresh()->isArchived())->toBeTrue()
        ->and($list->get('mountedActions'))->toBe([]);

    $archivedList = Livewire::test(ListCatalogServices::class)->filterTable('archived', true);

    expect(actionOffered($archivedList, sectionAction(editCatalogPanel($service), 'archive')))->toBeFalse();

    $archivedList->callAction(sectionAction(editCatalogPanel($service), 'unarchive'));

    expect($service->refresh()->isArchived())->toBeFalse();
});

it('offers delete next to archive for an unused catalog service and deletes it', function () {
    $service = CatalogService::factory()->create();

    $list = Livewire::test(ListCatalogServices::class);

    expect(actionOffered($list, sectionAction(editCatalogPanel($service), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editCatalogPanel($service), 'archive')))->toBeTrue();

    $list->callAction(sectionAction(editCatalogPanel($service), 'delete', danger: true));

    expect(CatalogService::query()->find($service->id))->toBeNull();
});

it('offers only archive for a catalog service that is in a package or was sold', function () {
    $inPackage = CatalogService::factory()->create();
    Package::factory()->create()->catalogServices()->attach($inPackage);
    $sold = CatalogService::factory()->create();
    CustomerService::factory()->forCatalogService($sold)->create();

    $list = Livewire::test(ListCatalogServices::class);

    foreach ([$inPackage, $sold] as $service) {
        expect(actionOffered($list, sectionAction(editCatalogPanel($service), 'delete', danger: true)))->toBeFalse()
            ->and(actionOffered($list, sectionAction(editCatalogPanel($service), 'archive')))->toBeTrue();
    }
});

it('offers delete and unarchive for an archived catalog service that is not in use', function () {
    $service = CatalogService::factory()->create();
    $service->archive();

    $list = Livewire::test(ListCatalogServices::class)->filterTable('archived', true);

    expect(actionOffered($list, sectionAction(editCatalogPanel($service), 'delete', danger: true)))->toBeTrue()
        ->and(actionOffered($list, sectionAction(editCatalogPanel($service), 'unarchive')))->toBeTrue();
});
