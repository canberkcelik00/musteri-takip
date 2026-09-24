<?php

use App\Actions\Charges\CreateCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Support\Money;

/**
 * Record a payment for a customer without open charges, so all of it becomes credit balance.
 */
function creditBalancePayment(Customer $customer, int $amount, Currency $currency = Currency::Try, string $paidOn = '2026-03-10'): Payment
{
    return app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => $currency,
        'paid_on' => $paidOn,
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

function createChargeFor(Customer $customer, int $amount, Currency $currency = Currency::Try): Charge
{
    return app(CreateCharge::class)->handle($customer, [
        'description' => 'Kurumsal Paket 2027–2028',
        'amount' => $amount,
        'currency' => $currency,
        'due_date' => '2027-03-01',
    ]);
}

it('creates the charge for the customer', function () {
    $customer = Customer::factory()->create();

    $charge = createChargeFor($customer, 400000);

    expect($charge->customer_id)->toBe($customer->id)
        ->and($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->toDateString())->toBe('2027-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->allocations()->exists())->toBeFalse();
});

it('closes the charge and keeps the rest when the balance is larger', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment($customer, 500000);

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->remainingAmount())->toBe(0)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});

it('closes the charge and uses up the balance when they are equal', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment($customer, 400000);

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->remainingAmount())->toBe(0)
        ->and($customer->creditBalances())->toBe([]);
});

it('leaves the charge partially paid when the balance is smaller', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment($customer, 100000);

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->isPartiallyPaid())->toBeTrue()
        ->and($charge->remainingAmount())->toBe(300000)
        ->and($customer->creditBalances())->toBe([]);
});

it('does not use a balance in another currency', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment($customer, 50000, Currency::Usd);

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->remainingAmount())->toBe(400000)
        ->and($charge->allocations()->exists())->toBeFalse()
        ->and($customer->creditBalances())->toEqual(['USD' => new Money(50000, Currency::Usd)]);
});

it('does not use the balance of another customer', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment(Customer::factory()->create(), 500000);

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->allocations()->exists())->toBeFalse();
});

it('uses the balance of the oldest payment first', function () {
    $customer = Customer::factory()->create();
    $newer = creditBalancePayment($customer, 300000, paidOn: '2026-05-01');
    $older = creditBalancePayment($customer, 200000, paidOn: '2026-04-01');

    $charge = createChargeFor($customer, 400000);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->allocations()->pluck('amount', 'payment_id')->all())->toBe([
            $older->id => 200000,
            $newer->id => 200000,
        ])
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});

it('marks the allocations as applied from the credit balance', function () {
    $customer = Customer::factory()->create();
    creditBalancePayment($customer, 500000);

    $charge = createChargeFor($customer, 400000);

    expect($charge->allocations()->get()->pluck('source')->all())->toBe([AllocationSource::CreditBalance])
        ->and(PaymentAllocation::query()->count())->toBe(1);
});
