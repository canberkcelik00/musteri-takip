<?php

use App\Actions\Charges\CreateCharge;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * An active 4.000 TL service for the period 01.03.2026 – 01.03.2027.
 *
 * @param  array<string, mixed>  $attributes
 */
function renewableService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

function createDueRenewalCharges(): Collection
{
    return app(CreateDueRenewalCharges::class)->handle();
}

it('creates no renewal charge the day before the renewal window starts', function () {
    renewableService();

    $this->travelTo('2027-01-29 23:59:00');

    expect(createDueRenewalCharges())->toBeEmpty()
        ->and(Charge::query()->count())->toBe(0);
});

it('creates the next period charge on the exact day the renewal window starts', function () {
    $customerService = renewableService();

    $this->travelTo('2027-01-30 00:00:00');

    $charges = createDueRenewalCharges();

    expect($charges)->toHaveCount(1);

    $charge = $charges->first();

    expect($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->description)->toBe('Kurumsal Paket 2027–2028')
        ->and($charge->amount)->toBe(400000)
        ->and($charge->currency)->toBe(Currency::Try)
        ->and($charge->due_date->toDateString())->toBe('2027-03-01')
        ->and($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->period_end->toDateString())->toBe('2028-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open);
});

it('uses the renewal lead days from the settings', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '10');
    renewableService();

    $this->travelTo('2027-02-18');
    expect(createDueRenewalCharges())->toBeEmpty();

    $this->travelTo('2027-02-19');
    expect(createDueRenewalCharges())->toHaveCount(1);
});

it('creates the charge of a service whose renewal window passed without one', function (string $today) {
    renewableService();

    $this->travelTo($today);

    expect(createDueRenewalCharges())->toHaveCount(1)
        ->and(Charge::query()->sole()->period_start->toDateString())->toBe('2027-03-01');
})->with([
    'inside the window' => '2027-02-15',
    'after the end date, period not advanced yet' => '2027-03-05',
]);

it('creates a single charge when run twice', function () {
    $customerService = renewableService();

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges())->toHaveCount(1)
        ->and(createDueRenewalCharges())->toBeEmpty()
        ->and(app(CreateRenewalCharge::class)->handle($customerService))->toBeNull()
        ->and($customerService->charges()->count())->toBe(1);
});

it('does not recreate a cancelled renewal charge', function () {
    $customerService = renewableService();
    Charge::factory()->cancelled()->create([
        'customer_id' => $customerService->customer_id,
        'customer_service_id' => $customerService->id,
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges())->toBeEmpty()
        ->and($customerService->charges()->count())->toBe(1);
});

it('creates the renewal charge next to the first period charge', function () {
    $customerService = renewableService();
    Charge::factory()->forCustomerService($customerService)->paid()->create();

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges())->toHaveCount(1)
        ->and($customerService->charges()->pluck('period_start')->map->toDateString()->sort()->values()->all())
        ->toBe(['2026-03-01', '2027-03-01']);
});

it('skips free and cancelled services', function () {
    renewableService(['is_free' => true]);
    CustomerService::factory()->cancelled()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges())->toBeEmpty()
        ->and(Charge::query()->count())->toBe(0);
});

it('skips the active services of an archived customer', function () {
    $customerService = renewableService();
    $customerService->customer->archive();

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges())->toBeEmpty()
        ->and(app(CreateRenewalCharge::class)->handle($customerService))->toBeNull()
        ->and(Charge::query()->count())->toBe(0);
});

it('returns null for a single service outside the renewal window or free', function () {
    $paid = renewableService();
    $free = renewableService(['is_free' => true]);

    $this->travelTo('2027-01-29');
    expect(app(CreateRenewalCharge::class)->handle($paid))->toBeNull();

    $this->travelTo('2027-01-30');
    expect(app(CreateRenewalCharge::class)->handle($free))->toBeNull()
        ->and(app(CreateRenewalCharge::class)->handle($paid))->toBeInstanceOf(Charge::class);
});

it('applies the customer credit balance to the renewal charge', function () {
    $customer = Customer::factory()->create();
    $customerService = renewableService(['customer_id' => $customer->id]);
    $firstCharge = Charge::factory()->forCustomerService($customerService)->create();

    $this->travelTo('2026-03-05');
    app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 500000,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-05',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect($firstCharge->fresh()->status)->toBe(ChargeStatus::Paid);

    $this->travelTo('2027-01-30');
    $charge = createDueRenewalCharges()->sole();

    expect($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->remainingAmount())->toBe(300000)
        ->and($customer->creditBalances())->toBe([]);
});

it('creates the charge for the customer of the service', function () {
    $customerService = renewableService();
    Customer::factory()->count(2)->create();

    $this->travelTo('2027-02-01');

    expect(createDueRenewalCharges()->sole()->customer_id)->toBe($customerService->customer_id);
});

it('keeps the charge customer check of CreateCharge for service charges', function () {
    $customerService = renewableService();

    expect(fn () => app(CreateCharge::class)->handle(Customer::factory()->create(), [
        'customer_service_id' => $customerService->id,
        'description' => 'Kurumsal Paket 2027–2028',
        'amount' => 400000,
        'currency' => Currency::Try,
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]))->toThrow(InvalidCharge::class);
});

it('logs a failing service and still creates the renewal charges of the others', function () {
    $broken = renewableService(['price' => 0]);
    $healthy = renewableService();
    Log::spy();

    $this->travelTo('2027-01-30 00:00:00');

    $failed = [];
    $charges = app(CreateDueRenewalCharges::class)->handle(function (CustomerService $customerService) use (&$failed): void {
        $failed[] = $customerService->id;
    });

    expect($charges)->toHaveCount(1)
        ->and($charges->sole()->customer_service_id)->toBe($healthy->id)
        ->and($failed)->toBe([$broken->id])
        ->and($broken->charges()->count())->toBe(0);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['customer_service_id'] === $broken->id);
});
