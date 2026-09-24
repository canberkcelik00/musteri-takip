<?php

use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;

function pay(Customer $customer, int $amount, Currency $currency = Currency::Try): Payment
{
    return Payment::factory()->for($customer)->create(['amount' => $amount, 'currency' => $currency]);
}

function allocate(Payment $payment, int $amount): void
{
    $charge = Charge::factory()->for($payment->customer)->create([
        'amount' => $amount,
        'currency' => $payment->currency,
    ]);

    $payment->allocations()->create(['charge_id' => $charge->id, 'amount' => $amount]);
}

it('has no balance without payments', function () {
    expect(Customer::factory()->create()->creditBalances())->toBe([]);
});

it('keeps an overpayment as credit balance', function () {
    $customer = Customer::factory()->create();
    allocate(pay($customer, 500000), 400000);

    $balances = $customer->creditBalances();

    expect(array_keys($balances))->toBe(['TRY'])
        ->and($balances['TRY']->amount)->toBe(100000)
        ->and($balances['TRY']->format())->toBe('1.000 TL');
});

it('keeps the balance of each currency separately', function () {
    $customer = Customer::factory()->create();
    allocate(pay($customer, 500000), 400000);
    pay($customer, 20000);
    allocate(pay($customer, 35000, Currency::Usd), 10000);

    $balances = $customer->creditBalances();

    expect(array_keys($balances))->toBe(['TRY', 'USD'])
        ->and($balances['TRY']->amount)->toBe(120000)
        ->and($balances['USD']->amount)->toBe(25000)
        ->and($balances['USD']->currency)->toBe(Currency::Usd);
});

it('leaves out a currency whose payments are fully allocated', function () {
    $customer = Customer::factory()->create();
    allocate(pay($customer, 400000), 400000);
    pay($customer, 10000, Currency::Eur);

    expect(array_keys($customer->creditBalances()))->toBe(['EUR']);
});

it('does not count payments of other customers', function () {
    $customer = Customer::factory()->create();
    pay($customer, 100000);
    pay(Customer::factory()->create(), 900000);

    expect($customer->creditBalances()['TRY']->amount)->toBe(100000);
});
