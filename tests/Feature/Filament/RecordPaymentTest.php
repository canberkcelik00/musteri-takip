<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * Logi's example (PRD): 1.500 TL hosting due first, 400 TL domain due later.
 *
 * @return array{customer: Customer, hosting: Charge, domain: Charge}
 */
function logiWithTwoCharges(): array
{
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    return [
        'customer' => $customer,
        'hosting' => Charge::factory()->for($customer)->create(['description' => 'Hosting', 'amount' => 150000, 'due_date' => '2026-05-01']),
        'domain' => Charge::factory()->for($customer)->create(['description' => 'Alan adı', 'amount' => 40000, 'due_date' => '2026-06-01']),
    ];
}

function paymentsPage()
{
    return Livewire::test(ListPayments::class);
}

function cardPayments(Customer $customer)
{
    return Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

function paymentRowAction(string $name, Payment $payment): TestAction
{
    return TestAction::make($name)->table($payment);
}

it('lists the payments of all customers, newest first', function () {
    $older = Payment::factory()->create(['paid_on' => '2026-05-01']);
    $newer = Payment::factory()->create(['paid_on' => '2026-06-01']);

    paymentsPage()
        ->assertOk()
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('fills the date, currency and method when a customer is chosen in the topbar form', function () {
    ['customer' => $customer] = logiWithTwoCharges();
    Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'due_date' => '2026-01-01']);
    $card = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    Payment::factory()->for($customer)->create(['payment_method_id' => $card->id, 'paid_on' => '2026-06-01']);

    topbarActions()
        ->mountAction('recordPayment')
        ->assertSchemaComponentVisible('customer_id')
        ->assertSchemaStateSet(['paid_on' => '2026-06-15', 'show_allocations' => false])
        ->fillForm(['customer_id' => $customer->id])
        ->assertSchemaStateSet([
            'currency' => Currency::Usd,
            'payment_method_id' => $card->id,
        ]);
});

it('starts with TL and the first method before a customer is chosen', function () {
    $first = PaymentMethod::query()->notArchived()->orderBy('sort_order')->firstOrFail();

    topbarActions()
        ->mountAction('recordPayment')
        ->assertSchemaStateSet(['currency' => Currency::Try, 'payment_method_id' => $first->id, 'paid_on' => '2026-06-15']);
});

it('records the payment and tells how much debt was closed and how much went to the credit balance', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiWithTwoCharges();

    topbarActions()
        ->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '2.500', 'currency' => 'TRY'])
        ->assertNotified('1.900 TL borç kapatıldı, 600 TL alacak bakiyesine eklendi.');

    $payment = $customer->payments()->sole();

    expect($payment->amount)->toBe(250000)
        ->and($payment->paid_on->toDateString())->toBe('2026-06-15')
        ->and($hosting->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($domain->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($customer->creditBalances()['TRY']->amount)->toBe(60000);
});

it('tells only the closed debt when nothing is left over', function () {
    ['customer' => $customer] = logiWithTwoCharges();

    topbarActions()
        ->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '1.000', 'currency' => 'TRY'])
        ->assertNotified('1.000 TL borç kapatıldı.');
});

it('tells only the credit balance when there is no open debt', function () {
    $customer = Customer::factory()->create();

    topbarActions()
        ->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '750,50', 'currency' => 'TRY'])
        ->assertNotified('750,50 TL alacak bakiyesine eklendi.');

    expect($customer->creditBalances()['TRY']->amount)->toBe(75050);
});

it('allocates automatically, oldest due date first, when the preview stays closed', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiWithTwoCharges();

    topbarActions()
        ->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '1.000', 'currency' => 'TRY']);

    expect($hosting->refresh()->allocatedAmount())->toBe(100000)
        ->and($domain->refresh()->allocatedAmount())->toBe(0);
});

it('suggests the automatic split in the preview and applies the changed rows', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiWithTwoCharges();

    topbarActions()
        ->mountAction('recordPayment')
        ->fillForm(['customer_id' => $customer->id, 'amount' => '1.000', 'show_allocations' => true])
        ->assertSchemaStateSet([
            "allocations.{$hosting->id}" => '1.000',
            "allocations.{$domain->id}" => null,
        ])
        ->fillForm(["allocations.{$domain->id}" => '400', "allocations.{$hosting->id}" => '600'])
        ->callMountedAction()
        ->assertNotified('1.000 TL borç kapatıldı.');

    expect($hosting->refresh()->allocatedAmount())->toBe(60000)
        ->and($domain->refresh()->allocatedAmount())->toBe(40000)
        ->and($domain->status)->toBe(ChargeStatus::Paid);
});

it('puts the rest of a manual allocation on the other open charges', function () {
    ['customer' => $customer, 'hosting' => $hosting, 'domain' => $domain] = logiWithTwoCharges();

    topbarActions()
        ->mountAction('recordPayment')
        ->fillForm(['customer_id' => $customer->id, 'amount' => '1.000', 'show_allocations' => true])
        ->fillForm(["allocations.{$hosting->id}" => null, "allocations.{$domain->id}" => '400'])
        ->callMountedAction();

    expect($domain->refresh()->allocatedAmount())->toBe(40000)
        ->and($hosting->refresh()->allocatedAmount())->toBe(60000);
});

it('refuses a manual allocation above the charge\'s remaining amount and records nothing', function () {
    ['customer' => $customer, 'domain' => $domain] = logiWithTwoCharges();

    topbarActions()
        ->mountAction('recordPayment')
        ->fillForm(['customer_id' => $customer->id, 'amount' => '1.000', 'show_allocations' => true])
        ->fillForm(["allocations.{$domain->id}" => '500'])
        ->callMountedAction()
        ->assertNotified('Bir borca kalan tutarından fazla dağıtılamaz.');

    expect(Payment::query()->count())->toBe(0)
        ->and(PaymentAllocation::query()->count())->toBe(0);
});

it('shows only the open charges of the chosen currency in the preview', function () {
    ['customer' => $customer, 'hosting' => $hosting] = logiWithTwoCharges();
    $usd = Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'amount' => 5000, 'due_date' => '2026-06-01']);

    topbarActions()
        ->mountAction('recordPayment')
        ->fillForm(['customer_id' => $customer->id, 'show_allocations' => true, 'currency' => 'USD', 'amount' => '30'])
        ->assertSchemaStateSet(["allocations.{$usd->id}" => '30'])
        ->assertSchemaComponentExists("allocations.{$usd->id}")
        ->assertSchemaComponentDoesNotExist("allocations.{$hosting->id}");
});

it('records a payment from the customer card with the customer field hidden', function () {
    ['customer' => $customer, 'hosting' => $hosting] = logiWithTwoCharges();
    $other = Customer::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->mountAction('recordPayment')
        ->assertSchemaComponentHidden('customer_id')
        ->assertSchemaStateSet(['currency' => Currency::Try, 'paid_on' => '2026-06-15'])
        ->fillForm(['amount' => '1.500'])
        ->callMountedAction()
        ->assertNotified('1.500 TL borç kapatıldı.');

    expect($customer->payments()->count())->toBe(1)
        ->and($other->payments()->count())->toBe(0)
        ->and($hosting->refresh()->status)->toBe(ChargeStatus::Paid);
});

it('asks for a confirmation before deleting a payment and reopens the charges it paid', function () {
    ['customer' => $customer, 'hosting' => $hosting] = logiWithTwoCharges();
    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->callAction('recordPayment', ['amount' => '1.500', 'currency' => 'TRY']);
    $payment = $customer->payments()->sole();

    expect($hosting->refresh()->status)->toBe(ChargeStatus::Paid);

    cardPayments($customer)
        ->mountAction(paymentRowAction('deletePayment', $payment))
        ->assertActionMounted(paymentRowAction('deletePayment', $payment));

    expect(Payment::query()->count())->toBe(1);

    cardPayments($customer)
        ->callAction(paymentRowAction('deletePayment', $payment))
        ->assertNotified('Ödeme silindi.');

    expect(Payment::query()->count())->toBe(0)
        ->and(PaymentAllocation::query()->count())->toBe(0)
        ->and($hosting->refresh()->status)->toBe(ChargeStatus::Open);
});

it('deletes a payment from the payments page', function () {
    ['customer' => $customer] = logiWithTwoCharges();
    topbarActions()->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '400', 'currency' => 'TRY']);
    $payment = $customer->payments()->sole();

    paymentsPage()
        ->assertCanSeeTableRecords([$payment])
        ->callAction(paymentRowAction('deletePayment', $payment))
        ->assertNotified('Ödeme silindi.');

    expect(Payment::query()->count())->toBe(0);
});

it('refreshes the card\'s charges and balance when a payment is recorded or deleted', function () {
    ['customer' => $customer] = logiWithTwoCharges();

    Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)
        ->assertOk();

    Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)
        ->assertOk();
});

it('records a payment from the topbar and shows it everywhere the customer\'s debt or payments are listed', function () {
    ['customer' => $customer, 'hosting' => $hosting] = logiWithTwoCharges();

    topbarActions()->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '1.500', 'currency' => 'TRY']);
    $payment = $customer->payments()->sole();

    // The list, the payments page, the card's payments and the card itself all listened for the
    // topbar's event and show the fresh state, even though the payment was recorded elsewhere.
    Livewire::test(ListCustomers::class)
        ->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)
        ->assertOk()
        ->assertSee('Logi');

    paymentsPage()
        ->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)
        ->assertOk()
        ->assertCanSeeTableRecords([$payment]);

    cardPayments($customer)
        ->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)
        ->assertOk()
        ->assertCanSeeTableRecords([$payment]);

    expect($hosting->refresh()->status)->toBe(ChargeStatus::Paid);
});
