<?php

use App\Actions\Charges\SplitCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;

/**
 * An open 4.000 TL charge for the period 01.03.2027 – 01.03.2028 of a customer service.
 */
function periodChargeToSplit(): Charge
{
    $customerService = CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2027-03-01',
        'end_date' => '2028-03-01',
    ]);

    return Charge::factory()->forCustomerService($customerService)->create();
}

function splitCharge(Charge $charge, int $amount, string $dueDate = '2027-06-01', ?string $description = null): Charge
{
    return app(SplitCharge::class)->handle($charge, $amount, $dueDate, $description);
}

it('keeps the total when a charge is split', function () {
    $charge = periodChargeToSplit();

    $part = splitCharge($charge, 150000);

    expect($charge->fresh()->amount)->toBe(250000)
        ->and($part->amount)->toBe(150000)
        ->and($charge->fresh()->amount + $part->amount)->toBe(400000);
});

it('creates the new part for the same customer, service and currency without a period', function () {
    $charge = periodChargeToSplit();

    $part = splitCharge($charge, 150000, '2027-06-01');

    expect($part->id)->not->toBe($charge->id)
        ->and($part->customer_id)->toBe($charge->customer_id)
        ->and($part->customer_service_id)->toBe($charge->customer_service_id)
        ->and($part->customer_id)->toBe($charge->customerService->customer_id)
        ->and($part->currency)->toBe(Currency::Try)
        ->and($part->due_date->toDateString())->toBe('2027-06-01')
        ->and($part->period_start)->toBeNull()
        ->and($part->period_end)->toBeNull()
        ->and($part->description)->toBe('Kurumsal Paket 2027–2028')
        ->and($part->status)->toBe(ChargeStatus::Open);
});

it('keeps the period, due date and description of the original charge', function () {
    $charge = periodChargeToSplit();

    splitCharge($charge, 150000, description: 'Kurumsal Paket 2. taksit');

    $original = $charge->fresh();

    expect($original->period_start->toDateString())->toBe('2027-03-01')
        ->and($original->period_end->toDateString())->toBe('2028-03-01')
        ->and($original->due_date->toDateString())->toBe('2027-03-01')
        ->and($original->description)->toBe('Kurumsal Paket 2027–2028')
        ->and(Charge::query()->latest('id')->value('description'))->toBe('Kurumsal Paket 2. taksit');
});

it('rejects a split amount that is not between zero and the charge amount', function (int $amount) {
    $charge = periodChargeToSplit();

    expect(fn () => splitCharge($charge, $amount))
        ->toThrow(InvalidCharge::class, __('charges.invalid_split_amount'));

    expect($charge->fresh()->amount)->toBe(400000)
        ->and(Charge::query()->count())->toBe(1);
})->with([0, -100, 400000, 500000]);

it('does not split a charge with allocations', function () {
    $charge = periodChargeToSplit();
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2027-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect(fn () => splitCharge($charge, 150000))
        ->toThrow(InvalidCharge::class, __('charges.has_allocations'));

    expect($charge->fresh()->amount)->toBe(400000)
        ->and(Charge::query()->count())->toBe(1);
});

it('does not split a charge that is not open', function (string $state) {
    $charge = Charge::factory()->{$state}()->create(['amount' => 400000]);

    expect(fn () => splitCharge($charge, 150000))
        ->toThrow(InvalidCharge::class, __('charges.not_open'));

    expect($charge->fresh()->amount)->toBe(400000)
        ->and(Charge::query()->count())->toBe(1);
})->with(['paid', 'cancelled']);

it('applies the credit balance to the new part', function () {
    $charge = periodChargeToSplit();
    $charge->customer->payments()->create([
        'amount' => 50000,
        'currency' => Currency::Try,
        'paid_on' => '2027-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    $part = splitCharge($charge, 150000);

    expect($part->remainingAmount())->toBe(100000)
        ->and($part->isPartiallyPaid())->toBeTrue()
        ->and($charge->customer->creditBalances())->toBe([]);
});
