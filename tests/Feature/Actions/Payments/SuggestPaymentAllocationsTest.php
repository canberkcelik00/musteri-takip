<?php

use App\Actions\Payments\SuggestPaymentAllocations;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;

it('suggests the allocation oldest due date first without saving anything', function () {
    $customer = Customer::factory()->create();
    $domain = Charge::factory()->for($customer)->create(['amount' => 40000, 'due_date' => '2026-04-01']);
    $hosting = Charge::factory()->for($customer)->create(['amount' => 150000, 'due_date' => '2026-03-01']);

    $suggestion = app(SuggestPaymentAllocations::class)->handle($customer, Currency::Try, 250000);

    expect($suggestion)->toBe([$hosting->id => 150000, $domain->id => 40000])
        ->and(PaymentAllocation::query()->exists())->toBeFalse()
        ->and($hosting->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($domain->fresh()->status)->toBe(ChargeStatus::Open);
});

it('completes manual lines with the oldest other charges', function () {
    $customer = Customer::factory()->create();
    $hosting = Charge::factory()->for($customer)->create(['amount' => 150000, 'due_date' => '2026-03-01']);
    $domain = Charge::factory()->for($customer)->create(['amount' => 40000, 'due_date' => '2026-04-01']);

    $suggestion = app(SuggestPaymentAllocations::class)->handle($customer, Currency::Try, 100000, [$domain->id => 40000]);

    expect($suggestion)->toBe([$domain->id => 40000, $hosting->id => 60000]);
});

it('rejects a payment amount that is not positive', function () {
    $customer = Customer::factory()->create();

    expect(fn () => app(SuggestPaymentAllocations::class)->handle($customer, Currency::Try, 0))
        ->toThrow(InvalidPaymentAllocation::class, 'Tutar sıfırdan büyük olmalıdır.');
});
