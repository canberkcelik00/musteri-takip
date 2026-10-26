<?php

use App\Actions\Charges\SplitCharge;
use App\Actions\CustomerServices\AdvanceDueCustomerServicePeriods;
use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\CustomerServices\ReactivateCustomerService;
use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;

/**
 * An active service for the period 01.03.2026 – 01.03.2027; its renewal window starts on 30.01.2027.
 *
 * @param  array<string, mixed>  $attributes
 */
function flaggedService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->create([
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

it('marks the renewal charge the scheduler creates', function () {
    $customerService = flaggedService();
    $this->travelTo('2027-02-10');

    $charge = app(CreateDueRenewalCharges::class)->handle()->sole();

    expect($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->fresh()->is_renewal)->toBeTrue();
});

it('marks the renewal charge created for a period the scheduler missed', function () {
    $customerService = flaggedService();
    $this->travelTo('2027-03-05');

    app(AdvanceDueCustomerServicePeriods::class)->handle();

    $charge = Charge::query()->where('customer_service_id', $customerService->id)->sole();

    expect($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->is_renewal)->toBeTrue();
});

it('marks the renewal charge created when the free flag is removed', function () {
    $customerService = flaggedService(['is_free' => true]);
    $this->travelTo('2027-02-15');

    $charge = app(RemoveCustomerServiceFreeFlag::class)->handle($customerService);

    expect($charge)->not->toBeNull()
        ->and($charge->fresh()->is_renewal)->toBeTrue();
});

it('marks the renewal charge created when a service is reactivated inside its period', function () {
    $customerService = flaggedService();
    $this->travelTo('2026-06-15');
    app(CancelCustomerService::class)->handle($customerService, cancelNote: 'Müşteri vazgeçti.');
    $this->travelTo('2027-02-15');

    $charges = app(ReactivateCustomerService::class)->handle($customerService, ['reopen_upcoming_charge' => false]);

    expect($charges)->toHaveCount(1)
        ->and($charges->sole()->fresh()->is_renewal)->toBeTrue();
});

it('keeps the mark and the id of a renewal charge that is reopened on reactivation', function () {
    $customerService = flaggedService();
    $this->travelTo('2027-02-10');
    $renewal = app(CreateRenewalCharge::class)->handle($customerService);
    app(CancelCustomerService::class)->handle($customerService, cancelNote: 'Müşteri vazgeçti.');

    expect($renewal->fresh()->status)->toBe(ChargeStatus::Cancelled);

    app(ReactivateCustomerService::class)->handle($customerService);

    $renewal->refresh();

    expect($renewal->status)->toBe(ChargeStatus::Open)
        ->and($renewal->is_renewal)->toBeTrue()
        ->and(Charge::query()->where('customer_service_id', $customerService->id)->count())->toBe(1);
});

it('does not mark the first period charge of an assigned service', function () {
    $this->travelTo('2027-02-10');
    $customerService = app(AssignCustomerService::class)->handle(Customer::factory()->create(), CatalogService::factory()->create(), ['start_date' => '2027-02-20']);

    expect(Charge::query()->where('customer_service_id', $customerService->id)->sole()->is_renewal)->toBeFalse();
});

it('does not mark the first period charge created when a service is reactivated after its period ended', function () {
    $customerService = flaggedService();
    $this->travelTo('2026-06-15');
    app(CancelCustomerService::class)->handle($customerService, cancelNote: 'Müşteri vazgeçti.');
    $this->travelTo('2027-04-01');

    $charges = app(ReactivateCustomerService::class)->handle($customerService, ['start_date' => '2027-04-01']);

    expect($charges)->toHaveCount(1)
        ->and($charges->sole()->period_start->toDateString())->toBe('2027-04-01')
        ->and($charges->sole()->fresh()->is_renewal)->toBeFalse();
});

it('does not mark the part split off a renewal charge', function () {
    $customerService = flaggedService();
    $this->travelTo('2027-02-10');
    $renewal = app(CreateRenewalCharge::class)->handle($customerService);

    $part = app(SplitCharge::class)->handle(actor(), $renewal, 100000, '2027-06-01');

    expect($part->fresh()->is_renewal)->toBeFalse()
        ->and($renewal->fresh()->is_renewal)->toBeTrue();
});
