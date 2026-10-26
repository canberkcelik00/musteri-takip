<?php

use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\InvalidCustomerService;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;

/**
 * A free, active 4.000 TL service for the period 01.03.2026 – 01.03.2027; its renewal
 * window starts on 30.01.2027.
 *
 * @param  array<string, mixed>  $attributes
 */
function freeService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->free()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

function removeFreeFlag(CustomerService $customerService, ?int $price = null): ?Charge
{
    return app(RemoveCustomerServiceFreeFlag::class)->handle($customerService, $price);
}

it('creates the upcoming period charge at once when the flag is removed within the renewal window', function () {
    $customerService = freeService();

    $this->travelTo('2027-02-15');

    $charge = removeFreeFlag($customerService);

    expect($customerService->fresh()->is_free)->toBeFalse()
        ->and($charge)->not->toBeNull()
        ->and($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->description)->toBe('Kurumsal Paket 2027–2028')
        ->and($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->toDateString())->toBe('2027-03-01')
        ->and($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->period_end->toDateString())->toBe('2028-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and(Charge::query()->count())->toBe(1);
});

it('creates the upcoming period charge when the flag is removed on the day the renewal window starts', function () {
    $customerService = freeService();

    $this->travelTo('2027-01-30 00:00:00');

    expect(removeFreeFlag($customerService)?->period_start->toDateString())->toBe('2027-03-01');
});

it('creates no charge when the flag is removed before the renewal window and leaves it to the renewal run', function () {
    $customerService = freeService();

    $this->travelTo('2027-01-29 23:59:00');

    expect(removeFreeFlag($customerService))->toBeNull()
        ->and($customerService->fresh()->is_free)->toBeFalse()
        ->and(Charge::query()->count())->toBe(0);

    $this->travelTo('2027-01-30');

    $charge = app(CreateDueRenewalCharges::class)->handle()->sole();

    expect($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->period_start->toDateString())->toBe('2027-03-01');
});

it('never charges the current period that started while the service was free', function () {
    $customerService = freeService();

    $this->travelTo('2026-06-01');

    expect(removeFreeFlag($customerService))->toBeNull()
        ->and(Charge::query()->count())->toBe(0);
});

it('advances the dates while still free when the period end passed without a scheduler run', function () {
    $customerService = freeService();

    $this->travelTo('2027-03-10');

    expect(removeFreeFlag($customerService))->toBeNull();

    $customerService->refresh();

    expect($customerService->is_free)->toBeFalse()
        ->and($customerService->start_date->toDateString())->toBe('2027-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2028-03-01')
        ->and(Charge::query()->count())->toBe(0);
});

it('charges only the upcoming period after advancing past periods that started while free', function () {
    $customerService = freeService();

    $this->travelTo('2028-02-15');

    $charge = removeFreeFlag($customerService);

    expect($customerService->fresh()->end_date->toDateString())->toBe('2028-03-01')
        ->and($charge?->period_start->toDateString())->toBe('2028-03-01')
        ->and(Charge::query()->count())->toBe(1);
});

it('creates a single charge when called twice', function () {
    $customerService = freeService();

    $this->travelTo('2027-02-15');

    removeFreeFlag($customerService);

    expect(removeFreeFlag($customerService))->toBeNull()
        ->and(Charge::query()->count())->toBe(1);
});

it('rejects a zero price without a new price and changes nothing', function () {
    $customerService = freeService(['price' => 0]);

    $this->travelTo('2027-02-15');

    expect(fn () => removeFreeFlag($customerService))->toThrow(InvalidCustomerService::class);

    $customerService->refresh();

    expect($customerService->is_free)->toBeTrue()
        ->and($customerService->price)->toBe(0)
        ->and(Charge::query()->count())->toBe(0);
});

it('rejects a new price that is not positive', function (int $price) {
    $customerService = freeService();

    $this->travelTo('2027-02-15');

    expect(fn () => removeFreeFlag($customerService, $price))->toThrow(InvalidCustomerService::class)
        ->and($customerService->fresh()->is_free)->toBeTrue()
        ->and(Charge::query()->count())->toBe(0);
})->with([0, -100]);

it('uses the new price for the service and the upcoming period charge', function () {
    $customerService = freeService(['price' => 0]);

    $this->travelTo('2027-02-15');

    $charge = removeFreeFlag($customerService, 250000);

    expect($customerService->fresh()->price)->toBe(250000)
        ->and($charge?->amount)->toBe(250000);
});

it('applies the customer credit balance to the upcoming period charge', function () {
    $customerService = freeService();
    $customer = $customerService->customer;

    $this->travelTo('2027-02-15');

    app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2027-02-15',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    $charge = removeFreeFlag($customerService);

    expect($charge?->remainingAmount())->toBe(300000)
        ->and($customer->creditBalances())->toBe([]);
});

it('reopens the cancelled upcoming period charge instead of creating a second one', function () {
    $customerService = freeService();

    $cancelled = Charge::factory()->forCustomerService($customerService)->cancelled()->create([
        'amount' => 300000,
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    $this->travelTo('2027-02-15');

    $charge = removeFreeFlag($customerService);

    expect($charge?->id)->toBe($cancelled->id)
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->amount)->toBe(400000)
        ->and($charge->description)->toBe('Kurumsal Paket 2027–2028')
        ->and($charge->cancelled_at)->toBeNull()
        ->and($charge->cancel_reason)->toBeNull()
        ->and(Charge::query()->count())->toBe(1);
});

it('leaves a cancelled upcoming period charge with an allocation cancelled and creates no second charge', function () {
    $customerService = freeService();

    $cancelled = Charge::factory()->forCustomerService($customerService)->cancelled()->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    $this->travelTo('2027-02-15');

    $payment = $customerService->customer->payments()->create([
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2027-02-15',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
    $payment->allocations()->create(['charge_id' => $cancelled->id, 'amount' => 100000]);

    expect(removeFreeFlag($customerService))->toBeNull()
        ->and($cancelled->fresh()->status)->toBe(ChargeStatus::Cancelled)
        ->and(Charge::query()->count())->toBe(1);
});

it('removes the flag of a cancelled service without creating a charge', function () {
    $customerService = freeService();
    $customerService->update(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-01']);

    $this->travelTo('2027-02-15');

    expect(removeFreeFlag($customerService))->toBeNull()
        ->and($customerService->fresh()->is_free)->toBeFalse()
        ->and(Charge::query()->count())->toBe(0);
});
