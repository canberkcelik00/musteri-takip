<?php

use App\Actions\Charges\CreateManualCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;

/**
 * @param  array<string, mixed>  $attributes
 */
function createManualCharge(Customer $customer, array $attributes = []): Charge
{
    return app(CreateManualCharge::class)->handle($customer, [
        'description' => 'Ek e-posta hesabı kurulumu',
        'amount' => 75000,
        'currency' => Currency::Try,
        'due_date' => '2027-04-01',
        ...$attributes,
    ]);
}

it('creates a charge without a customer service or period', function () {
    $customer = Customer::factory()->create();

    $charge = createManualCharge($customer);

    expect($charge->customer_id)->toBe($customer->id)
        ->and($charge->customer_service_id)->toBeNull()
        ->and($charge->period_start)->toBeNull()
        ->and($charge->period_end)->toBeNull()
        ->and($charge->description)->toBe('Ek e-posta hesabı kurulumu')
        ->and($charge->amount)->toBe(75000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->toDateString())->toBe('2027-04-01')
        ->and($charge->status)->toBe(ChargeStatus::Open);
});

it('creates a charge for a service of the same customer without a period', function () {
    $customerService = CustomerService::factory()->create();

    $charge = createManualCharge($customerService->customer, ['customer_service_id' => $customerService->id]);

    expect($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->period_start)->toBeNull()
        ->and($charge->period_end)->toBeNull();
});

it('rejects a charge for a service of another customer', function () {
    $customerService = CustomerService::factory()->create();

    expect(fn () => createManualCharge(Customer::factory()->create(), ['customer_service_id' => $customerService->id]))
        ->toThrow(InvalidCharge::class, __('charges.customer_service_of_another_customer'));

    expect(Charge::query()->exists())->toBeFalse();
});

it('applies the credit balance to the charge', function () {
    $customer = Customer::factory()->create();
    app(RecordPayment::class)->handle($customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2027-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    $charge = createManualCharge($customer);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and($customer->creditBalances()['TRY']->amount)->toBe(25000);
});

it('rejects an empty description', function (string $description) {
    expect(fn () => createManualCharge(Customer::factory()->create(), ['description' => $description]))
        ->toThrow(InvalidCharge::class, __('charges.description_required'));

    expect(Charge::query()->exists())->toBeFalse();
})->with(['', '   ']);

it('rejects an amount of zero or less', function (int $amount) {
    expect(fn () => createManualCharge(Customer::factory()->create(), ['amount' => $amount]))
        ->toThrow(InvalidCharge::class, __('charges.amount_not_positive'));

    expect(Charge::query()->exists())->toBeFalse();
})->with([0, -100]);
