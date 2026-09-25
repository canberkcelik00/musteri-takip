<?php

use App\Actions\Charges\UpdateCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;

function recordTryPaymentForUpdate(Customer $customer, int $amount): Payment
{
    return app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

/**
 * @param  array{amount?: int, due_date?: string}  $attributes
 */
function updateCharge(Charge $charge, array $attributes): Charge
{
    return app(UpdateCharge::class)->handle($charge, $attributes);
}

it('changes the amount and due date of an open charge', function () {
    $charge = Charge::factory()->create(['amount' => 400000, 'due_date' => '2027-03-01']);

    $updated = updateCharge($charge, ['amount' => 350000, 'due_date' => '2027-04-15']);

    expect($updated->amount)->toBe(350000)
        ->and($updated->due_date->toDateString())->toBe('2027-04-15')
        ->and($updated->status)->toBe(ChargeStatus::Open)
        ->and($charge->fresh()->amount)->toBe(350000);
});

it('does not change the amount of a partially paid charge', function () {
    $charge = Charge::factory()->create(['amount' => 400000]);
    recordTryPaymentForUpdate($charge->customer, 100000);

    expect(fn () => updateCharge($charge, ['amount' => 300000]))
        ->toThrow(InvalidCharge::class, __('charges.has_allocations'));

    expect($charge->fresh()->amount)->toBe(400000);
});

it('does not change the amount of a charge partly paid from the credit balance', function () {
    $customer = Customer::factory()->create();
    recordTryPaymentForUpdate($customer, 100000);
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000]);
    updateCharge($charge, ['due_date' => '2027-03-01']);

    expect($charge->fresh()->remainingAmount())->toBe(300000)
        ->and(fn () => updateCharge($charge, ['amount' => 450000]))
        ->toThrow(InvalidCharge::class, __('charges.has_allocations'));

    expect($charge->fresh()->amount)->toBe(400000);
});

it('does not change the due date either when the amount of a partially paid charge is rejected', function () {
    $charge = Charge::factory()->create(['amount' => 400000, 'due_date' => '2027-03-01']);
    recordTryPaymentForUpdate($charge->customer, 100000);

    expect(fn () => updateCharge($charge, ['amount' => 300000, 'due_date' => '2027-05-01']))
        ->toThrow(InvalidCharge::class);

    expect($charge->fresh()->due_date->toDateString())->toBe('2027-03-01');
});

it('changes the due date of a partially paid charge while its amount stays', function () {
    $charge = Charge::factory()->create(['amount' => 400000, 'due_date' => '2027-03-01']);
    recordTryPaymentForUpdate($charge->customer, 100000);

    $updated = updateCharge($charge, ['amount' => 400000, 'due_date' => '2027-05-01']);

    expect($updated->due_date->toDateString())->toBe('2027-05-01')
        ->and($updated->amount)->toBe(400000)
        ->and($updated->status)->toBe(ChargeStatus::Open)
        ->and($updated->remainingAmount())->toBe(300000);
});

it('does not change the due date of a paid or cancelled charge', function (string $state) {
    $charge = Charge::factory()->{$state}()->create(['due_date' => '2027-03-01']);

    expect(fn () => updateCharge($charge, ['due_date' => '2027-05-01']))
        ->toThrow(InvalidCharge::class, __('charges.not_open'));

    expect($charge->fresh()->due_date->toDateString())->toBe('2027-03-01');
})->with(['paid', 'cancelled']);

it('rejects an amount of zero or less', function (int $amount) {
    $charge = Charge::factory()->create(['amount' => 400000]);

    expect(fn () => updateCharge($charge, ['amount' => $amount]))
        ->toThrow(InvalidCharge::class, __('charges.amount_not_positive'));

    expect($charge->fresh()->amount)->toBe(400000);
})->with([0, -100]);

it('applies the credit balance after the amount changes', function () {
    $customer = Customer::factory()->create();
    recordTryPaymentForUpdate($customer, 100000);
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000]);

    $updated = updateCharge($charge, ['amount' => 80000]);

    expect($updated->status)->toBe(ChargeStatus::Paid)
        ->and($updated->remainingAmount())->toBe(0)
        ->and($customer->creditBalances()['TRY']->amount)->toBe(20000);
});
