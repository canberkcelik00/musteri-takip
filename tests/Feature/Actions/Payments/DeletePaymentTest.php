<?php

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

function recordPaymentToDelete(Customer $customer, int $amount): Payment
{
    return app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

it('reopens the charges a deleted payment closed and removes its allocations', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-03-01']);
    $payment = recordPaymentToDelete($customer, 500000);

    app(DeletePayment::class)->handle($payment);

    $charge->refresh();

    expect(Payment::find($payment->id))->toBeNull()
        ->and(PaymentAllocation::query()->where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->remainingAmount())->toBe(400000)
        ->and($customer->creditBalances())->toBe([]);
});

it('keeps the other payment of a charge paid by two payments', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-03-01']);
    $first = recordPaymentToDelete($customer, 150000);
    $second = recordPaymentToDelete($customer, 250000);
    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid);

    app(DeletePayment::class)->handle($second);

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->remainingAmount())->toBe(250000)
        ->and($charge->isPartiallyPaid())->toBeTrue()
        ->and($first->allocations()->pluck('amount', 'charge_id')->all())->toBe([$charge->id => 150000]);
});

it('does not reopen a charge the deleted payment did not touch', function () {
    $customer = Customer::factory()->create();
    $paidCharge = Charge::factory()->for($customer)->create(['amount' => 10000, 'due_date' => '2026-01-01']);
    recordPaymentToDelete($customer, 10000);
    $payment = recordPaymentToDelete($customer, 5000);

    app(DeletePayment::class)->handle($payment);

    expect($paidCharge->fresh()->status)->toBe(ChargeStatus::Paid);
});

it('applies the remaining credit balance to the reopened charges, oldest due date first', function () {
    $customer = Customer::factory()->create();
    $older = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-02-01']);
    $newer = Charge::factory()->for($customer)->create(['amount' => 300000, 'due_date' => '2026-03-01']);
    $deleted = recordPaymentToDelete($customer, 150000);
    $withBalance = recordPaymentToDelete($customer, 350000);
    expect($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);

    app(DeletePayment::class)->handle($deleted);

    $older->refresh();
    $newer->refresh();

    expect($older->status)->toBe(ChargeStatus::Paid)
        ->and($older->allocations()->where('source', AllocationSource::CreditBalance)->sum('amount'))->toEqual(100000)
        ->and($newer->status)->toBe(ChargeStatus::Open)
        ->and($newer->remainingAmount())->toBe(50000)
        ->and($newer->allocations()->where('source', AllocationSource::CreditBalance)->exists())->toBeFalse()
        ->and($withBalance->allocations()->sum('amount'))->toEqual(350000)
        ->and($customer->creditBalances())->toBe([]);
});

it('closes the reopened charge from the balance and keeps the rest as balance', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-03-01']);
    $deleted = recordPaymentToDelete($customer, 100000);
    recordPaymentToDelete($customer, 500000);

    app(DeletePayment::class)->handle($deleted);

    $charge->refresh();

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($charge->remainingAmount())->toBe(0)
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(100000, Currency::Try)]);
});

it('returns how many paid charges it reopened, none for a charge that was only partly paid', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-03-01']);
    Charge::factory()->for($customer)->create(['amount' => 200000, 'due_date' => '2026-03-02']);
    $closesBoth = recordPaymentToDelete($customer, 300000);

    expect(app(DeletePayment::class)->handle($closesBoth))->toBe(2);

    $partly = recordPaymentToDelete($customer, 60000);

    expect(app(DeletePayment::class)->handle($partly))->toBe(0);
});
