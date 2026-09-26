<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
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

function chargesPage()
{
    return Livewire::test(ListCharges::class);
}

function chargesOnCard(Customer $customer)
{
    return Livewire::test(ChargesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

function chargeRowAction(string $name, Charge $charge): TestAction
{
    return TestAction::make($name)->table($charge);
}

/**
 * Pay part of a charge so that it has an allocation.
 */
function payPartOf(Charge $charge, int $amount): void
{
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => $amount,
        'currency' => $charge->currency,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ], [$charge->id => $amount]);
}

it('lists the open charges of all customers, oldest due date first', function () {
    $later = Charge::factory()->create(['due_date' => '2026-07-01']);
    $earlier = Charge::factory()->create(['due_date' => '2026-05-01']);
    $paid = Charge::factory()->paid()->create();
    $cancelled = Charge::factory()->cancelled()->create();

    chargesPage()
        ->assertOk()
        ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
        ->assertCanNotSeeTableRecords([$paid, $cancelled]);
});

it('filters the list to overdue charges only', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-14']);
    $dueToday = Charge::factory()->create(['due_date' => '2026-06-15']);
    $upcoming = Charge::factory()->create(['due_date' => '2026-07-01']);

    chargesPage()
        ->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$dueToday, $upcoming]);
});

it('does not count a fully allocated charge as overdue in the filter', function () {
    $customer = Customer::factory()->create();
    $settled = Charge::factory()->for($customer)->create(['amount' => 10000, 'due_date' => '2026-06-01']);
    $partial = Charge::factory()->for($customer)->create(['amount' => 10000, 'due_date' => '2026-06-02']);
    payPartOf($settled, 10000);
    payPartOf($partial, 4000);

    chargesPage()
        ->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$settled]);
});

it('filters the list by currency', function () {
    $lira = Charge::factory()->create(['currency' => Currency::Try]);
    $dollar = Charge::factory()->create(['currency' => Currency::Usd]);

    chargesPage()
        ->filterTable('currency', 'USD')
        ->assertCanSeeTableRecords([$dollar])
        ->assertCanNotSeeTableRecords([$lira]);
});

it('locks the amount field of a charge with allocations and lets the due date change', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-20']);
    payPartOf($charge, 30000);

    chargesPage()
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentExists('amount', checkComponentUsing: fn (MoneyInput $field): bool => $field->isDisabled())
        ->fillForm(['due_date' => '2026-07-10'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge->refresh();

    expect($charge->amount)->toBe(100000)
        ->and($charge->due_date->toDateString())->toBe('2026-07-10');
});

it('leaves the amount field of a charge without allocations editable and changes the amount', function () {
    $charge = Charge::factory()->create(['amount' => 100000, 'due_date' => '2026-06-20']);

    chargesPage()
        ->mountAction(chargeRowAction('editCharge', $charge))
        ->assertSchemaComponentExists('amount', checkComponentUsing: fn (MoneyInput $field): bool => ! $field->isDisabled())
        ->fillForm(['amount' => '1.250,50', 'due_date' => '2026-07-01'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge->refresh();

    expect($charge->amount)->toBe(125050)
        ->and($charge->due_date->toDateString())->toBe('2026-07-01');
});

it('adds a manual charge for a chosen customer from the charges page', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    chargesPage()
        ->mountAction('createManualCharge')
        ->assertSchemaComponentVisible('customer_id')
        ->fillForm([
            'customer_id' => $customer->id,
            'description' => 'Ek destek saati',
            'amount' => '750',
            'currency' => 'TRY',
            'due_date' => '2026-06-30',
            'customer_service_id' => $service->id,
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Borç eklendi.');

    $charge = $customer->charges()->sole();

    expect($charge->description)->toBe('Ek destek saati')
        ->and($charge->amount)->toBe(75000)
        ->and($charge->customer_service_id)->toBe($service->id)
        ->and($charge->period_start)->toBeNull();
});

it('fixes the customer on the card and applies the credit balance to the new manual charge', function () {
    $customer = Customer::factory()->create();
    app(RecordPayment::class)->handle($customer, [
        'amount' => 20000,
        'currency' => Currency::Try,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ]);

    chargesOnCard($customer)
        ->mountAction(TestAction::make('createManualCharge')->table())
        ->assertSchemaComponentHidden('customer_id')
        ->fillForm([
            'description' => 'Kurulum',
            'amount' => '500',
            'currency' => 'TRY',
            'due_date' => '2026-06-30',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $charge = $customer->charges()->sole();

    expect($charge->allocatedAmount())->toBe(20000)
        ->and($charge->remainingAmount())->toBe(30000);
});

it('requires a description for a manual charge', function () {
    $customer = Customer::factory()->create();

    chargesOnCard($customer)
        ->mountAction(TestAction::make('createManualCharge')->table())
        ->fillForm(['description' => '', 'amount' => '500', 'currency' => 'TRY', 'due_date' => '2026-06-30'])
        ->callMountedAction()
        ->assertHasFormErrors(['description' => 'required']);

    expect($customer->charges()->count())->toBe(0);
});

it('splits a charge and keeps the total', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-20', 'description' => 'Hosting']);

    chargesOnCard($customer)
        ->mountAction(chargeRowAction('splitCharge', $charge))
        ->fillForm(['amount' => '400', 'due_date' => '2026-09-01'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Borç bölündü.');

    $piece = $customer->charges()->whereKeyNot($charge->id)->sole();

    expect($charge->refresh()->amount)->toBe(60000)
        ->and($piece->amount)->toBe(40000)
        ->and($piece->due_date->toDateString())->toBe('2026-09-01')
        ->and($piece->description)->toBe('Hosting');
});

it('refuses to split off the whole amount and leaves the charge unchanged', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000]);

    chargesOnCard($customer)
        ->mountAction(chargeRowAction('splitCharge', $charge))
        ->fillForm(['amount' => '1.000', 'due_date' => '2026-09-01'])
        ->callMountedAction()
        ->assertNotified('Ayrılan tutar sıfırdan büyük ve borç tutarından küçük olmalı.');

    expect($charge->refresh()->amount)->toBe(100000)
        ->and($customer->charges()->count())->toBe(1);
});

it('cancels a charge only with a reason', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create();

    chargesOnCard($customer)
        ->mountAction(chargeRowAction('cancelCharge', $charge))
        ->fillForm(['reason' => ''])
        ->callMountedAction()
        ->assertHasFormErrors(['reason' => 'required']);

    expect($charge->refresh()->status)->toBe(ChargeStatus::Open);

    chargesOnCard($customer)
        ->mountAction(chargeRowAction('cancelCharge', $charge))
        ->fillForm(['reason' => 'Yanlış girildi'])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Borç iptal edildi.');

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Cancelled)
        ->and($charge->cancel_reason)->toBe('Yanlış girildi');
});

it('hides split and cancel for a charge with allocations but keeps edit', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 100000]);
    payPartOf($charge, 30000);

    chargesPage()
        ->assertTableActionVisible('editCharge', $charge)
        ->assertTableActionHidden('splitCharge', $charge)
        ->assertTableActionHidden('cancelCharge', $charge);

    chargesOnCard($customer)
        ->assertTableActionVisible('editCharge', $charge)
        ->assertTableActionHidden('splitCharge', $charge)
        ->assertTableActionHidden('cancelCharge', $charge);
});

it('hides every charge action for paid and cancelled charges', function () {
    $customer = Customer::factory()->create();
    $paid = Charge::factory()->for($customer)->paid()->create();
    $cancelled = Charge::factory()->for($customer)->cancelled()->create();

    chargesPage()
        ->removeTableFilter('status')
        ->assertTableActionHidden('editCharge', $paid)
        ->assertTableActionHidden('splitCharge', $paid)
        ->assertTableActionHidden('cancelCharge', $paid)
        ->assertTableActionHidden('editCharge', $cancelled)
        ->assertTableActionHidden('splitCharge', $cancelled)
        ->assertTableActionHidden('cancelCharge', $cancelled);
});

it('refreshes the card and the list when a charge changes', function () {
    $customer = Customer::factory()->create();

    chargesOnCard($customer)
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();

    Livewire::test(ViewCustomer::class, ['record' => $customer->getKey()])
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();

    chargesPage()
        ->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT)
        ->assertOk();
});
