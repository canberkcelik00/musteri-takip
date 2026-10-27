<?php

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\UndoPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\PaymentCannotBeUndone;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;

function recordPaymentToUndo(User $actor, Customer $customer, int $amount): Payment
{
    return app(RecordPayment::class)->handle($actor, $customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    $this->user = User::factory()->admin()->create();
});

it('takes back a fresh payment with its allocations and reopens the charge', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-03-01']);
    $payment = recordPaymentToUndo($this->user, $customer, 400000);

    $this->travel(2)->minutes();

    expect(app(UndoPayment::class)->handle($this->user, $payment))->toBe(1)
        ->and(Payment::find($payment->id))->toBeNull()
        ->and(PaymentAllocation::query()->where('payment_id', $payment->id)->exists())->toBeFalse()
        ->and($charge->refresh()->status)->toBe(ChargeStatus::Open);
});

it('refuses a payment older than two minutes', function () {
    $customer = Customer::factory()->create();
    $payment = recordPaymentToUndo($this->user, $customer, 100000);

    $this->travel(2)->minutes();
    $this->travel(1)->seconds();

    expect(fn () => app(UndoPayment::class)->handle($this->user, $payment))->toThrow(PaymentCannotBeUndone::class)
        ->and(Payment::find($payment->id))->not->toBeNull();
});

it("refuses another user's payment", function () {
    $customer = Customer::factory()->create();
    $payment = recordPaymentToUndo($this->user, $customer, 100000);

    expect(fn () => app(UndoPayment::class)->handle(User::factory()->admin()->create(), $payment))->toThrow(PaymentCannotBeUndone::class)
        ->and(Payment::find($payment->id))->not->toBeNull();
});

it('refuses a payment that has no recorded user', function () {
    $payment = Payment::factory()->create(['recorded_by' => null]);

    expect(fn () => app(UndoPayment::class)->handle($this->user, $payment))->toThrow(PaymentCannotBeUndone::class);
});
