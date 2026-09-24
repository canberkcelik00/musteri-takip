<?php

use App\Actions\Charges\ApplyCreditBalance;
use App\Actions\Payments\DeletePayment;
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

function recordTryPayment(Customer $customer, int $amount): Payment
{
    return app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

/**
 * @return array<int, array{0: AllocationSource, 1: int}>
 */
function allocationRowsBetween(Payment $payment, Charge $charge): array
{
    return $payment->allocations()->where('charge_id', $charge->id)->orderBy('id')->get()
        ->map(fn (PaymentAllocation $allocation): array => [$allocation->source, $allocation->amount])
        ->all();
}

it('does not allocate twice when called again', function () {
    $customer = Customer::factory()->create();
    recordTryPayment($customer, 500000);
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000]);

    app(ApplyCreditBalance::class)->handle($charge);
    $charge = app(ApplyCreditBalance::class)->handle($charge);

    expect($charge->allocations()->count())->toBe(1)
        ->and($charge->allocatedAmount())->toBe(400000)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});

it('leaves cancelled and paid charges untouched', function (string $state, ChargeStatus $status) {
    $customer = Customer::factory()->create();
    recordTryPayment($customer, 500000);
    $charge = Charge::factory()->for($customer)->{$state}()->create(['amount' => 400000]);

    $charge = app(ApplyCreditBalance::class)->handle($charge);

    expect($charge->allocations()->exists())->toBeFalse()
        ->and($charge->status)->toBe($status)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(500000, Currency::Try)]);
})->with([
    'cancelled' => ['cancelled', ChargeStatus::Cancelled],
    'paid' => ['paid', ChargeStatus::Paid],
]);

it('keeps the allocation made with the payment apart from the one applied from the balance', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000]);
    $first = recordTryPayment($customer, 50000);
    $second = recordTryPayment($customer, 50000);
    $payment = recordTryPayment($customer, 500000);

    app(DeletePayment::class)->handle($first);
    $charge = app(ApplyCreditBalance::class)->handle($charge);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and(allocationRowsBetween($payment, $charge))->toBe([
            [AllocationSource::Payment, 300000],
            [AllocationSource::CreditBalance, 50000],
        ]);

    app(DeletePayment::class)->handle($second);
    $charge = app(ApplyCreditBalance::class)->handle($charge);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and(allocationRowsBetween($payment, $charge))->toBe([
            [AllocationSource::Payment, 300000],
            [AllocationSource::CreditBalance, 100000],
        ])
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});
