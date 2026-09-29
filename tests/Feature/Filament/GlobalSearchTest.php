<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Livewire\TopbarActions;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

/**
 * Logi's hosting sells under logi.com.tr (docs/UI.md §7's example).
 */
function logiWithDomain(): Customer
{
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logitech Bilgisayar Ürünleri Ltd. Şti.']);

    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create();

    $service->items()->first()->update([
        'details' => ['domain' => 'logi.com.tr', 'server' => 'srv14.natro.com', 'cpanel_username' => 'logicomt'],
    ]);

    return $customer;
}

/**
 * @return array<int, string>
 */
function searchTitles(string $term): array
{
    return CustomerResource::getGlobalSearchResults($term)->map->title->values()->all();
}

it('finds the customer by the domain name kept in a service item, case and accent insensitively', function () {
    logiWithDomain();
    Customer::factory()->create(['short_name' => 'Başka Müşteri']);

    expect(searchTitles('logi.com.tr'))->toBe(['Logi'])
        ->and(searchTitles('LOGI.COM.TR'))->toBe(['Logi'])
        ->and(searchTitles('baska.com.tr'))->toBe([]);
});

it('finds a customer by short name, legal title or a contact, with multi-word terms treated as AND', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logitech Bilgisayar Ürünleri Ltd. Şti.']);
    Contact::factory()->for($customer)->primary()->create(['name' => 'Elif Şahin', 'email' => 'muhasebe.logi@example.com']);
    Customer::factory()->create(['short_name' => 'Başka']);

    expect(searchTitles('Logi'))->toBe(['Logi'])
        ->and(searchTitles('Logitech'))->toBe(['Logi'])
        ->and(searchTitles('Elif Şahin'))->toBe(['Logi'])
        ->and(searchTitles('muhasebe.logi@example.com'))->toBe(['Logi'])
        ->and(searchTitles('Elif Yılmaz'))->toBe([]);
});

it('does not match the JSON key name, only the domain value', function () {
    logiWithDomain();

    expect(searchTitles('server'))->toBe([])
        ->and(searchTitles('cpanel_username'))->toBe([]);
});

it('finds the domain of a cancelled service too', function () {
    $customer = Customer::factory()->create(['short_name' => 'Eski Müşteri']);
    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Domain]))
        ->cancelled()
        ->create();
    $service->items()->first()->update(['details' => ['domain' => 'eskidomain.com']]);

    expect(searchTitles('eskidomain.com'))->toBe(['Eski Müşteri']);
});

it('includes an archived customer, labelled, without the "Ödeme al" shortcut', function () {
    $customer = Customer::factory()->archived()->create(['short_name' => 'Arşivli']);

    $results = CustomerResource::getGlobalSearchResults('Arşivli');

    expect($results)->toHaveCount(1);

    $result = $results->first();

    expect($result->details[__('archive.status')] ?? null)->toBe(__('archive.status_archived'))
        ->and($result->getVisibleActions())->toBeEmpty();
});

it('shows an "Ödeme al" shortcut for a non-archived customer, dispatching the topbar event', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    $result = CustomerResource::getGlobalSearchResults('Logi')->sole();
    $actions = $result->getVisibleActions();

    expect($actions)->toHaveCount(1);
    expect($actions[0]->getName())->toBe('recordPayment');
});

it('shows the customer\'s title and up to three distinct domain names in the result details', function () {
    $customer = logiWithDomain();
    CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Domain]))
        ->create()
        ->items()->first()->update(['details' => ['domain' => 'logi.com.tr']]); // duplicate, must not repeat

    $result = CustomerResource::getGlobalSearchResults('Logi')->sole();

    expect($result->details[__('customers.legal_title')])->toBe('Logitech Bilgisayar Ürünleri Ltd. Şti.')
        ->and($result->details[__('customers.domains')])->toBe('logi.com.tr');
});

it('links the result to the customer card', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    $result = CustomerResource::getGlobalSearchResults('Logi')->sole();

    expect($result->url)->toBe(CustomerResource::getUrl('view', ['record' => $customer]));
});

it('keeps catalog services, packages and payment methods out of Ctrl+K, only in their own lists', function () {
    expect(CatalogServiceResource::canGloballySearch())->toBeFalse()
        ->and(PackageResource::canGloballySearch())->toBeFalse()
        ->and(PaymentMethodResource::canGloballySearch())->toBeFalse()
        ->and(CustomerResource::canGloballySearch())->toBeTrue();
});

it('binds Ctrl+K (mod+k) to focus the search box on a panel page', function () {
    $this->get(CustomerResource::getUrl('index'))
        ->assertOk()
        ->assertSee('x-mousetrap.global.mod-k', false);
});

it('opens the topbar payment form with the customer pre-selected when the search result event fires', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Charge::factory()->for($customer)->create(['amount' => 50000, 'due_date' => '2026-06-01', 'currency' => 'USD']);
    $method = PaymentMethod::query()->notArchived()->orderBy('sort_order')->firstOrFail();

    $this->travelTo('2026-06-15 10:00:00');

    Livewire::test(TopbarActions::class)
        ->call('openRecordPayment', $customer->id)
        ->assertActionMounted('recordPayment')
        ->assertSchemaStateSet([
            'customer_id' => $customer->id,
            'currency' => Currency::Usd,
            'payment_method_id' => $method->id,
        ])
        ->assertSchemaComponentVisible('customer_id')
        ->fillForm(['amount' => '500'])
        ->callMountedAction()
        ->assertDispatched(RecordPaymentAction::PAYMENTS_CHANGED_EVENT);

    expect($customer->payments()->sole()->amount)->toBe(50000);
});
