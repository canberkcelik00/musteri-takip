<?php

use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use Illuminate\Database\QueryException;

it('casts amount, currency and payment date', function () {
    $customer = Customer::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();

    $payment = $customer->payments()->create([
        'amount' => 500000,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-01',
        'payment_method_id' => $paymentMethod->id,
    ])->fresh();

    expect($payment->amount)->toBe(500000)
        ->and($payment->currency)->toBe(Currency::Try)
        ->and($payment->paid_on->format('d.m.Y'))->toBe('01.03.2026')
        ->and($payment->customer->is($customer))->toBeTrue()
        ->and($payment->paymentMethod->is($paymentMethod))->toBeTrue()
        ->and($customer->payments->modelKeys())->toBe([$payment->id]);
});

it('links a payment and a charge through an allocation', function () {
    $allocation = PaymentAllocation::factory()->create();

    expect($allocation->amount)->toBe($allocation->charge->amount)
        ->and($allocation->payment->allocations->modelKeys())->toBe([$allocation->id])
        ->and($allocation->charge->allocations->modelKeys())->toBe([$allocation->id])
        ->and($allocation->payment->customer_id)->toBe($allocation->charge->customer_id);
});

it('deletes its allocations with it and keeps the charge', function () {
    $allocation = PaymentAllocation::factory()->create();
    $charge = $allocation->charge;

    $allocation->payment->delete();

    expect(PaymentAllocation::count())->toBe(0)
        ->and(Charge::find($charge->id))->not->toBeNull();
});

it('allocates a payment to a charge only once per source', function () {
    $allocation = PaymentAllocation::factory()->create();

    expect(fn () => $allocation->payment->allocations()->create([
        'charge_id' => $allocation->charge_id,
        'amount' => 100,
    ]))->toThrow(QueryException::class);
});

it('is not saved with a zero amount', function () {
    $customer = Customer::factory()->create();

    expect(fn () => $customer->payments()->create([
        'amount' => 0,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-01',
        'payment_method_id' => PaymentMethod::factory()->create()->id,
    ]))->toThrow(QueryException::class);
});

it('does not let a customer or payment method with payments be deleted', function () {
    $payment = PaymentAllocation::factory()->create()->payment;

    expect(fn () => $payment->paymentMethod->delete())->toThrow(QueryException::class)
        ->and(fn () => $payment->customer->delete())->toThrow(QueryException::class);
});
