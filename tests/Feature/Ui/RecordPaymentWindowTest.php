<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Livewire\Windows\RecordPayment;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * Logi's example (PRD): 1.500 TL hosting due first, 400 TL domain due later.
 *
 * @return array{customer: Customer, hosting: Charge, domain: Charge}
 */
function logiOwingTwoCharges(): array
{
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    return [
        'customer' => $customer,
        'hosting' => Charge::factory()->for($customer)->create(['description' => 'Hosting', 'amount' => 150000, 'due_date' => '2026-05-01']),
        'domain' => Charge::factory()->for($customer)->create(['description' => 'Alan adı', 'amount' => 40000, 'due_date' => '2026-06-01']),
    ];
}

it('opens from the top bar button and the sidebar-less pages through the window host', function () {
    $this->get(route('app.today'))
        ->assertOk()
        ->assertSee("name: 'record-payment'", false)
        ->assertSee("name: 'create-customer'", false);
});

it('fills the date, currency and method when a customer is chosen', function () {
    ['customer' => $customer] = logiOwingTwoCharges();
    Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'due_date' => '2026-01-01']);
    $card = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    Payment::factory()->for($customer)->create(['payment_method_id' => $card->id, 'paid_on' => '2026-06-01']);

    Livewire::test(RecordPayment::class)
        ->assertSet('paid_on', '2026-06-15')
        ->assertSet('show_allocations', false)
        ->set('customer_id', (string) $customer->id)
        ->assertSet('currency', 'USD')
        ->assertSet('payment_method_id', (string) $card->id);
});

it('starts with TL and the first method before a customer is chosen', function () {
    $first = PaymentMethod::query()->notArchived()->orderBy('sort_order')->orderBy('id')->firstOrFail();

    Livewire::test(RecordPayment::class)
        ->assertSet('currency', 'TRY')
        ->assertSet('payment_method_id', (string) $first->id)
        ->assertSet('customer_id', '');
});

it('opens with the customer chosen but still changeable', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::test(RecordPayment::class, ['customer' => $customer->id])
        ->assertSet('customer_id', (string) $customer->id)
        ->assertSet('fixed', false)
        ->assertSee(__('payments.customer'));
});

it('hides the customer field when the customer is fixed, as on the customer card', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::test(RecordPayment::class, ['customer' => $customer->id, 'fixed' => true])
        ->assertSet('fixed', true)
        ->assertDontSee(__('payments.customer'));
});

it('takes the currency the opener asks for', function () {
    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class, ['customer' => $customer->id, 'currency' => 'EUR'])
        ->assertSet('currency', 'EUR');
});

it('records the payment and tells how much debt was closed and how much went to the credit balance', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiOwingTwoCharges();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '2.500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('payments-changed')
        ->assertDispatched('close-window')
        ->assertDispatched('notify', message: '1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi.');

    $payment = $customer->payments()->sole();

    expect($payment->amount)->toBe(250000)
        ->and($payment->paid_on->toDateString())->toBe('2026-06-15')
        ->and($hosting->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($domain->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($customer->creditBalances()['TRY']->amount)->toBe(60000);
});

it('tells only the closed debt when nothing is left over', function () {
    ['customer' => $customer] = logiOwingTwoCharges();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '1.000')
        ->call('save')
        ->assertDispatched('notify', message: '1.000 TL borç kapatıldı.');
});

it('tells only the credit balance when there is no open debt', function () {
    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '750,50')
        ->call('save')
        ->assertDispatched('notify', message: '750,50 TL alacak bakiyesine eklendi.');

    expect($customer->creditBalances()['TRY']->amount)->toBe(75050);
});

it('allocates automatically, oldest due date first, when the preview stays closed', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiOwingTwoCharges();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '1.000')
        ->call('save');

    expect($hosting->refresh()->allocatedAmount())->toBe(100000)
        ->and($domain->refresh()->allocatedAmount())->toBe(0);
});

it('validates the customer, the amount and the method', function () {
    Livewire::test(RecordPayment::class)
        ->set('payment_method_id', '')
        ->call('save')
        ->assertHasErrors(['customer_id' => 'required', 'amount' => 'required', 'payment_method_id' => 'required']);

    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', 'abc')
        ->call('save')
        ->assertHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

it('refuses an amount of zero under the amount field and records nothing', function () {
    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['amount' => __('payments.amount_not_positive')])
        ->assertNotDispatched('close-window');

    expect(Payment::query()->count())->toBe(0);
});

it('does not take a payment for an archived customer', function () {
    $customer = Customer::factory()->archived()->create();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '100')
        ->call('save')
        ->assertHasErrors('customer_id');

    expect(Payment::query()->count())->toBe(0);
});

it('suggests the automatic split in the preview and applies the changed rows', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiOwingTwoCharges();

    $component = Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '1.200')
        ->set('show_allocations', true)
        ->assertSet("allocations.{$hosting->id}", '1.200')
        ->assertSet("allocations.{$domain->id}", '')
        ->assertSee('Hosting')
        ->assertSee('Alan adı');

    $component
        ->set("allocations.{$hosting->id}", '800')
        ->set("allocations.{$domain->id}", '400')
        ->call('save')
        ->assertHasNoErrors();

    expect($hosting->refresh()->allocatedAmount())->toBe(80000)
        ->and($domain->refresh()->allocatedAmount())->toBe(40000);
});

it('puts the rest of a manual allocation on the other open charges', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiOwingTwoCharges();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '1.000')
        ->set('show_allocations', true)
        ->set("allocations.{$domain->id}", '300')
        ->set("allocations.{$hosting->id}", '')
        ->call('save');

    expect($domain->refresh()->allocatedAmount())->toBe(30000)
        ->and($hosting->refresh()->allocatedAmount())->toBe(70000);
});

it('refuses a manual allocation above the charge\'s remaining amount and records nothing', function () {
    ['customer' => $customer, 'domain' => $domain] = logiOwingTwoCharges();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('amount', '2.000')
        ->set('show_allocations', true)
        ->set("allocations.{$domain->id}", '500')
        ->call('save')
        ->assertHasErrors(['amount' => __('payments.exceeds_charge_remaining')]);

    expect(Payment::query()->count())->toBe(0);
});

it('shows only the open charges of the chosen currency in the preview', function () {
    ['customer' => $customer] = logiOwingTwoCharges();
    $usd = Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'amount' => 5000, 'description' => 'Dolar borcu', 'due_date' => '2026-06-18']);

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('show_allocations', true)
        ->assertSee('Hosting')
        ->assertDontSee('Dolar borcu')
        ->set('currency', 'USD')
        ->assertSee('Dolar borcu')
        ->assertDontSee('Hosting')
        ->assertSet("allocations.{$usd->id}", '');
});

it('tells the customer has no open charge in the currency, in the preview', function () {
    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class)
        ->set('customer_id', (string) $customer->id)
        ->set('show_allocations', true)
        ->assertSee(__('payments.no_open_charges'));
});

it('does not let the browser unfix the customer', function () {
    $customer = Customer::factory()->create();

    Livewire::test(RecordPayment::class, ['customer' => $customer->id, 'fixed' => true])->set('fixed', false);
})->throws('Cannot update locked property');
