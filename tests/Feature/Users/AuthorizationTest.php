<?php

use App\Enums\Currency;
use App\Livewire\Pages\Customers\Index as CustomersIndex;
use App\Livewire\Pages\Customers\Show;
use App\Livewire\Topbar\Search;
use App\Livewire\WindowHost;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\RecordPayment;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Quote;
use App\Models\User;
use App\Support\Ui\Navigation;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function developer(): User
{
    return User::factory()->developer()->create();
}

function administrator(): User
{
    return User::factory()->admin()->create();
}

/**
 * A customer with a 4.000 TL service and an open 1.500 TL charge.
 */
function customerWithMoney(): Customer
{
    $customer = Customer::factory()->create(['short_name' => 'Para Müşterisi']);
    $service = CustomerService::factory()->for($customer)->create(['name' => 'Kurumsal Paket', 'price' => 400000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->for($service)->create(['amount' => 150000, 'currency' => Currency::Try]);

    return $customer;
}

dataset('money routes', [
    'open charges' => ['app.charges.index'],
    'payments' => ['app.payments.index'],
    'quotes' => ['app.quotes.index'],
    'catalog services' => ['app.catalog.services'],
    'catalog packages' => ['app.catalog.packages'],
    'settings' => ['app.settings'],
    'payment methods' => ['app.payment-methods'],
    'servers' => ['app.servers'],
]);

it('forbids a developer every money route', function (string $route) {
    $this->actingAs(developer())->get(route($route))->assertForbidden();
})->with('money routes');

it('lets an administrator open every money route', function (string $route) {
    $this->actingAs(administrator())->get(route($route))->assertOk();
})->with('money routes');

it('forbids a developer the quote PDF', function () {
    $quote = Quote::factory()->create();

    $this->actingAs(developer())->get(route('app.quotes.pdf', $quote))->assertForbidden();
});

it('sends a developer from the home page to the customers', function () {
    $this->actingAs(developer())->get(route('app.today'))->assertRedirect(route('app.customers.index'));
    $this->actingAs(administrator())->get(route('app.today'))->assertOk();
});

it('lets a developer open the customers and a customer card', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(developer())->get(route('app.customers.index'))->assertOk();
    $this->actingAs(developer())->get(route('app.customers.show', $customer))->assertOk();
});

it('does not open a money window for a developer through the window host', function (string $window) {
    Livewire::actingAs(developer())->test(WindowHost::class)
        ->dispatch('open-window', name: $window)
        ->assertSet('name', null);
})->with(['record-payment', 'edit-charge', 'view-payment', 'edit-service', 'assign-service', 'create-quote', 'edit-server', 'edit-catalog-service']);

it('opens a customer window for a developer through the window host', function () {
    Livewire::actingAs(developer())->test(WindowHost::class)
        ->dispatch('open-window', name: 'create-customer')
        ->assertSet('name', 'create-customer');
});

it('forbids a developer to mount a money window directly', function (string $window) {
    Livewire::actingAs(developer())->test(config("ui.windows.{$window}"))->assertForbidden();
})->with(['record-payment', 'send-reminder', 'cancel-service', 'quote', 'edit-package', 'pull-from-server']);

it('forbids a window action once the permission is gone', function () {
    $user = administrator();
    $window = Livewire::actingAs($user)->test(RecordPayment::class);

    $user->syncRoles('developer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $window->call('save')->assertForbidden();
});

it('asks for the permission of each confirmation', function () {
    Livewire::actingAs(developer())->test(Confirm::class, ['action' => 'delete-payment', 'id' => 1])->assertForbidden();
    Livewire::actingAs(developer())->test(Confirm::class, ['action' => 'archive-customer', 'id' => Customer::factory()->create()->id])->assertOk();
});

it('shows a developer the customer card without any amount', function () {
    $customer = customerWithMoney();

    $html = Livewire::actingAs(developer())->test(Show::class, ['customer' => $customer])
        ->assertSee('Para Müşterisi')
        ->assertSee('Kurumsal Paket')
        ->html();

    expect($html)->not->toContain('TL')
        ->not->toContain('Açık borç')
        ->not->toContain('Son ödemeler')
        ->not->toContain('record-payment')
        ->not->toContain('edit-service');
});

it('shows an administrator the amounts on the customer card', function () {
    $customer = customerWithMoney();

    Livewire::actingAs(administrator())->test(Show::class, ['customer' => $customer])
        ->assertSee('4.000 TL')
        ->assertSee('1.500 TL')
        ->assertSee('Açık borç');
});

it('shows a developer the customer list without the debt column or an amount', function () {
    customerWithMoney();

    $html = Livewire::actingAs(developer())->test(CustomersIndex::class)->assertSee('Para Müşterisi')->html();

    expect($html)->not->toContain('TL')->not->toContain('Açık borç')->not->toContain('record-payment');
});

it('shows an administrator the debt column on the customer list', function () {
    customerWithMoney();

    Livewire::actingAs(administrator())->test(CustomersIndex::class)->assertSee('1.500 TL')->assertSee('Açık borç');
});

it('does not give a developer the payment shortcut in the search', function () {
    customerWithMoney();

    $html = Livewire::actingAs(developer())->test(Search::class)->set('query', 'Para')->assertSee('Para Müşterisi')->html();

    expect($html)->not->toContain('record-payment');
});

it('leaves the money items out of a developer menu and tab bar', function () {
    $this->actingAs(developer());

    $labels = collect(Navigation::entries(route('app.customers.index')))
        ->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry])
        ->pluck('label')
        ->all();

    expect($labels)->toBe(['ui.nav.customers', 'ui.nav.tasks'])
        ->and(collect(Navigation::tabs())->pluck('tab')->all())->toBe(['ui.nav.tab_customers', 'ui.nav.tab_tasks']);
});

it('shows an administrator the whole menu', function () {
    $this->actingAs(administrator());

    $labels = collect(Navigation::entries(route('app.today')))
        ->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry])
        ->pluck('label');

    expect($labels)->toContain('ui.nav.today', 'ui.nav.quotes', 'ui.nav.payments', 'ui.nav.servers');
});

it('hides the top bar payment button from a developer', function () {
    $this->actingAs(developer())->get(route('app.customers.index'))
        ->assertOk()
        ->assertDontSee("name: 'record-payment'", false)
        ->assertSee("name: 'create-customer'", false);
});
