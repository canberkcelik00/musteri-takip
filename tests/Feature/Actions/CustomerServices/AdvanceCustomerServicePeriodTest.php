<?php

use App\Actions\CustomerServices\AdvanceCustomerServicePeriod;
use App\Actions\CustomerServices\AdvanceDueCustomerServicePeriods;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * An active 4.000 TL service for the period 01.03.2026 – 01.03.2027.
 *
 * @param  array<string, mixed>  $attributes
 */
function advanceableService(array $attributes = []): CustomerService
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

function advanceDueCustomerServicePeriods(): Collection
{
    return app(AdvanceDueCustomerServicePeriods::class)->handle();
}

/**
 * @return array{0: string, 1: string}
 */
function servicePeriod(CustomerService $customerService): array
{
    $customerService->refresh();

    return [$customerService->start_date->toDateString(), $customerService->end_date->toDateString()];
}

/**
 * @return list<string>
 */
function chargedPeriodStarts(CustomerService $customerService): array
{
    return $customerService->charges()->pluck('period_start')->map->toDateString()->sort()->values()->all();
}

it('does not advance the period the day before the end date', function () {
    $customerService = advanceableService();

    $this->travelTo('2027-02-28 23:59:00');

    expect(advanceDueCustomerServicePeriods())->toBeEmpty()
        ->and(servicePeriod($customerService))->toBe(['2026-03-01', '2027-03-01']);
});

it('advances the period by one period on the end date', function () {
    $customerService = advanceableService();

    $this->travelTo('2027-03-01 00:00:00');

    $advanced = advanceDueCustomerServicePeriods();

    expect($advanced)->toHaveCount(1)
        ->and($advanced->sole()->id)->toBe($customerService->id)
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01']);
});

it('advances the period regardless of the payment status', function (bool $paid) {
    $customerService = advanceableService();

    $this->travelTo('2027-01-30');
    $renewalCharge = app(CreateDueRenewalCharges::class)->handle()->sole();

    if ($paid) {
        $renewalCharge->update(['status' => ChargeStatus::Paid]);
    }

    $this->travelTo('2027-03-05');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01'])
        ->and($renewalCharge->fresh()->isOverdue())->toBe(! $paid)
        ->and($customerService->charges()->count())->toBe(1);
})->with([
    'renewal charge unpaid and overdue' => false,
    'renewal charge paid' => true,
]);

it('advances a free service without creating a charge', function () {
    $customerService = advanceableService(['is_free' => true]);

    $this->travelTo('2027-03-01');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01'])
        ->and(Charge::query()->exists())->toBeFalse();
});

it('does not advance a cancelled service', function () {
    $customerService = CustomerService::factory()->cancelled()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    $this->travelTo('2027-03-01');

    expect(advanceDueCustomerServicePeriods())->toBeEmpty()
        ->and(app(AdvanceCustomerServicePeriod::class)->handle($customerService))->toBeFalse()
        ->and(servicePeriod($customerService))->toBe(['2026-03-01', '2027-03-01'])
        ->and(Charge::query()->exists())->toBeFalse();
});

it('advances the period only once when run twice', function () {
    $customerService = advanceableService();

    $this->travelTo('2027-03-01');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(advanceDueCustomerServicePeriods())->toBeEmpty()
        ->and(app(AdvanceCustomerServicePeriod::class)->handle($customerService))->toBeFalse()
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01'])
        ->and($customerService->charges()->count())->toBe(1);
});

it('creates the missing renewal charge before advancing the period', function () {
    $customerService = advanceableService();

    $this->travelTo('2027-03-01');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01']);

    $charge = $customerService->charges()->sole();

    expect($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->amount)->toBe(400000)
        ->and($charge->due_date->toDateString())->toBe('2027-03-01')
        ->and($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->period_end->toDateString())->toBe('2028-03-01');
});

it('advances up to the current period and charges every skipped period', function () {
    $customerService = advanceableService(['start_date' => '2024-03-01', 'end_date' => '2025-03-01']);

    $this->travelTo('2026-09-25');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(servicePeriod($customerService))->toBe(['2026-03-01', '2027-03-01'])
        ->and(chargedPeriodStarts($customerService))->toBe(['2025-03-01', '2026-03-01']);

    expect(advanceDueCustomerServicePeriods())->toBeEmpty()
        ->and(servicePeriod($customerService))->toBe(['2026-03-01', '2027-03-01'])
        ->and($customerService->charges()->count())->toBe(2);
});

it('does not create a second charge for a period that already has one', function () {
    $customerService = advanceableService();
    Charge::factory()->cancelled()->create([
        'customer_id' => $customerService->customer_id,
        'customer_service_id' => $customerService->id,
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    $this->travelTo('2027-03-01');

    expect(advanceDueCustomerServicePeriods())->toHaveCount(1)
        ->and(servicePeriod($customerService))->toBe(['2027-03-01', '2028-03-01'])
        ->and($customerService->charges()->count())->toBe(1);
});

it('keeps a period starting on 28 February after a leap year', function () {
    $customerService = advanceableService(['is_free' => true, 'start_date' => '2028-02-29', 'end_date' => '2029-02-28']);

    $this->travelTo('2029-02-28');

    advanceDueCustomerServicePeriods();

    expect(servicePeriod($customerService))->toBe(['2029-02-28', '2030-02-28']);
});

it('logs a failing service and still advances the others', function () {
    $broken = advanceableService(['price' => 0]);
    $healthy = advanceableService();
    Log::spy();

    $this->travelTo('2027-03-01 00:00:00');

    $failed = [];
    $advanced = app(AdvanceDueCustomerServicePeriods::class)->handle(function (CustomerService $customerService) use (&$failed): void {
        $failed[] = $customerService->id;
    });

    expect($advanced->pluck('id')->all())->toBe([$healthy->id])
        ->and($failed)->toBe([$broken->id])
        ->and(servicePeriod($healthy))->toBe(['2027-03-01', '2028-03-01'])
        ->and(chargedPeriodStarts($healthy))->toBe(['2027-03-01'])
        ->and(servicePeriod($broken))->toBe(['2026-03-01', '2027-03-01'])
        ->and(chargedPeriodStarts($broken))->toBe([]);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['customer_service_id'] === $broken->id);
});
