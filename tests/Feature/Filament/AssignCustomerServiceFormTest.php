<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-03-01 10:00:00');
});

function assignForm(Customer $customer)
{
    return Livewire::test(ViewCustomer::class, ['record' => $customer->id]);
}

function assignAction(): TestAction
{
    return TestAction::make('assignService');
}

it('suggests price, currency and start date and creates the service, the item and the first charge', function () {
    $customer = Customer::factory()->create();
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create([
        'name' => 'Linux Hosting',
        'price' => Money::parseAmount('1.500'),
        'currency' => Currency::Usd,
    ]);

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$hosting->id}"])
        ->assertSchemaStateSet([
            'price' => '1.500',
            'currency' => Currency::Usd,
            'start_date' => '2026-03-01',
        ])
        ->fillForm([
            'price' => '1.200,50',
            "items.{$hosting->id}.details.domain" => 'logi.com.tr',
            "items.{$hosting->id}.details.server" => 'srv1',
            "items.{$hosting->id}.details.cpanel_username" => '',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $service = $customer->customerServices()->sole();
    $item = $service->items()->sole();
    $charge = $customer->charges()->sole();

    expect($service->name)->toBe('Linux Hosting')
        ->and($service->price)->toBe(120050)
        ->and($service->currency)->toBe(Currency::Usd)
        ->and($service->start_date->toDateString())->toBe('2026-03-01')
        ->and($service->end_date->toDateString())->toBe('2027-03-01')
        ->and($item->details)->toBe(['domain' => 'logi.com.tr', 'server' => 'srv1'])
        ->and($item->license_key)->toBeNull()
        ->and($charge->amount)->toBe(120050)
        ->and($charge->status)->toBe(ChargeStatus::Open);
});

it('assigns a package with one item per catalog service and a single charge', function () {
    $customer = Customer::factory()->create();
    $hosting = CatalogService::factory()->ofType(ServiceType::Hosting)->create();
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create();
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => Money::parseAmount('4.000'), 'currency' => Currency::Try]);
    $package->catalogServices()->attach([$hosting->id, $domain->id]);

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm([
            'sold' => "package:{$package->id}",
            "items.{$hosting->id}.details.server" => 'srv1',
            "items.{$domain->id}.details.domain" => 'logi.com.tr',
        ])
        ->assertSchemaStateSet(['price' => '4.000', 'currency' => Currency::Try])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $service = $customer->customerServices()->sole();

    expect($service->package_id)->toBe($package->id)
        ->and($service->price)->toBe(Money::parseAmount('4.000'))
        ->and($service->items()->count())->toBe(2)
        ->and($service->items()->where('catalog_service_id', $hosting->id)->sole()->details)->toBe(['server' => 'srv1'])
        ->and($service->items()->where('catalog_service_id', $domain->id)->sole()->details)->toBe(['domain' => 'logi.com.tr'])
        ->and($customer->charges()->sole()->amount)->toBe(Money::parseAmount('4.000'));
});

it('creates no charge when the first charge is skipped or the service is free', function (array $state) {
    $customer = Customer::factory()->create();
    $catalogService = CatalogService::factory()->create();

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$catalogService->id}", ...$state])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($customer->customerServices()->count())->toBe(1)
        ->and($customer->charges()->count())->toBe(0);
})->with([
    'skipped' => [['create_first_charge' => 0]],
    'free' => [['is_free' => true]],
]);

it('keeps the license key out of the details', function () {
    $customer = Customer::factory()->create();
    $license = CatalogService::factory()->ofType(ServiceType::License)->create();
    $domain = CatalogService::factory()->ofType(ServiceType::Domain)->create();

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$license->id}"])
        ->assertFormFieldExists("items.{$license->id}.license_key")
        ->assertFormFieldExists("items.{$license->id}.details.quantity")
        ->fillForm([
            "items.{$license->id}.license_key" => 'ABCD-1234',
            "items.{$license->id}.details.quantity" => '5',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $item = $customer->customerServices()->sole()->items()->sole();

    expect($item->license_key)->toBe('ABCD-1234')
        ->and($item->details)->toBe(['quantity' => '5']);

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$domain->id}"])
        ->assertFormFieldDoesNotExist("items.{$domain->id}.license_key");
});

it('lists archived catalog services and packages last in their group with an archived label', function () {
    CatalogService::factory()->archived()->create(['name' => 'Aaa Eski']);
    CatalogService::factory()->create(['name' => 'Zzz Yeni']);
    $package = Package::factory()->archived()->create(['name' => 'Eski Paket']);

    $options = null;

    assignForm(Customer::factory()->create())
        ->mountAction(assignAction())
        ->assertFormFieldExists('sold', function (Select $field) use (&$options): bool {
            $options = collect($field->getOptions());

            return true;
        });

    expect($options->keys()->all())->toBe(['Hizmetler', 'Paketler'])
        ->and(array_values($options['Hizmetler']))->toBe(['Zzz Yeni', 'Aaa Eski (arşivlendi)'])
        ->and(array_values($options['Paketler']))->toBe(['Eski Paket (arşivlendi)'])
        ->and(array_key_first($options['Paketler']))->toBe("package:{$package->id}");
});

it('rejects a start date whose period has already ended and creates nothing', function () {
    $customer = Customer::factory()->create();
    $catalogService = CatalogService::factory()->create();

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$catalogService->id}", 'start_date' => '2025-01-01'])
        ->callMountedAction()
        ->assertNotified('Başlangıç tarihi olarak hizmetin şu anki döneminin başlangıcını girin.')
        ->assertActionHalted(assignAction());

    expect($customer->customerServices()->count())->toBe(0)
        ->and($customer->charges()->count())->toBe(0);
});

it('warns for an archived customer and creates nothing', function () {
    $customer = Customer::factory()->archived()->create();
    $catalogService = CatalogService::factory()->create();

    assignForm($customer)
        ->mountAction(assignAction())
        ->fillForm(['sold' => "catalog_service:{$catalogService->id}"])
        ->callMountedAction()
        ->assertNotified('Önce müşteriyi arşivden çıkarın.');

    expect($customer->customerServices()->count())->toBe(0);
});
