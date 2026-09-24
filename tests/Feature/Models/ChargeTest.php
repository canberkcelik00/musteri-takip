<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentAllocation;
use Illuminate\Database\QueryException;

/**
 * @param  array<string, mixed>  $attributes
 */
function createPeriodCharge(CustomerService $customerService, array $attributes = []): Charge
{
    return $customerService->customer->charges()->create([
        'customer_service_id' => $customerService->id,
        'description' => 'Kurumsal Paket 2026–2027',
        'amount' => 400000,
        'currency' => Currency::Try,
        'due_date' => '2026-03-01',
        'period_start' => '2026-03-01',
        'period_end' => '2027-03-01',
        ...$attributes,
    ]);
}

it('casts amount, currency, dates and status', function () {
    $charge = createPeriodCharge(CustomerService::factory()->create())->fresh();

    expect($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->format('d.m.Y'))->toBe('01.03.2026')
        ->and($charge->period_start?->format('d.m.Y'))->toBe('01.03.2026')
        ->and($charge->period_end?->format('d.m.Y'))->toBe('01.03.2027')
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->cancelled_at)->toBeNull();
});

it('belongs to its customer and customer service', function () {
    $customerService = CustomerService::factory()->create();
    $charge = createPeriodCharge($customerService);

    expect($charge->customer->is($customerService->customer))->toBeTrue()
        ->and($charge->customerService->is($customerService))->toBeTrue()
        ->and($customerService->charges->modelKeys())->toBe([$charge->id])
        ->and($customerService->customer->charges->modelKeys())->toBe([$charge->id]);
});

it('cannot charge the same period of a customer service twice', function () {
    $customerService = CustomerService::factory()->create();
    createPeriodCharge($customerService);

    expect(fn () => createPeriodCharge($customerService, ['description' => 'Tekrar']))
        ->toThrow(QueryException::class)
        ->and(Charge::count())->toBe(1);
});

it('charges another period or another customer service of the same period', function () {
    $customerService = CustomerService::factory()->create();
    createPeriodCharge($customerService);

    createPeriodCharge($customerService, [
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);
    createPeriodCharge(CustomerService::factory()->create());

    expect(Charge::count())->toBe(3);
});

it('allows several manual charges without a period', function () {
    $customer = Customer::factory()->create();

    Charge::factory()->count(2)->for($customer)->create();

    expect($customer->charges()->count())->toBe(2);
});

it('is not saved with an invalid amount or period', function (array $attributes) {
    expect(fn () => createPeriodCharge(CustomerService::factory()->create(), $attributes))
        ->toThrow(QueryException::class)
        ->and(Charge::count())->toBe(0);
})->with([
    'zero amount' => [['amount' => 0]],
    'period start without end' => [['period_end' => null]],
    'period end without start' => [['period_start' => null]],
    'period end before start' => [['period_end' => '2026-02-01']],
    'period end on start' => [['period_end' => '2026-03-01']],
]);

it('does not let a customer or customer service with charges be deleted', function () {
    $customerService = CustomerService::factory()->create();
    createPeriodCharge($customerService);

    expect(fn () => $customerService->delete())->toThrow(QueryException::class)
        ->and(fn () => $customerService->customer->delete())->toThrow(QueryException::class);
});

it('does not let a charge with allocations be deleted', function () {
    $allocation = PaymentAllocation::factory()->create();

    expect(fn () => $allocation->charge->delete())->toThrow(QueryException::class);
});

it('has a factory state for a customer service period', function () {
    $customerService = CustomerService::factory()->create([
        'price' => 400000,
        'currency' => Currency::Usd,
        'start_date' => '2026-03-01',
    ]);

    $charge = Charge::factory()->forCustomerService($customerService)->create()->fresh();

    expect($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Usd)
        ->and($charge->due_date->format('d.m.Y'))->toBe('01.03.2026')
        ->and($charge->period_start?->format('d.m.Y'))->toBe('01.03.2026')
        ->and($charge->period_end?->format('d.m.Y'))->toBe('01.03.2027');
});
