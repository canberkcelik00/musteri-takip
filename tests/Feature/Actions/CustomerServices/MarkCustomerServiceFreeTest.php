<?php

use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\CustomerServices\MarkCustomerServiceFree;
use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * A paid, active 4.000 TL service for the period 01.03.2026 – 01.03.2027; its renewal
 * window starts on 30.01.2027.
 */
function serviceToMarkFree(): CustomerService
{
    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);
}

/**
 * @return Collection<int, Charge>
 */
function markFree(CustomerService $customerService, bool $cancelUpcomingCharges = true): Collection
{
    return app(MarkCustomerServiceFree::class)->handle($customerService, $cancelUpcomingCharges);
}

it('marks the service free and cancels the unpaid upcoming period charge by default', function () {
    $customerService = serviceToMarkFree();
    $currentCharge = Charge::factory()->forCustomerService($customerService)->create();

    $this->travelTo('2027-02-15');

    $upcomingCharge = app(CreateRenewalCharge::class)->handle($customerService);

    $cancelled = markFree($customerService);

    $upcomingCharge->refresh();

    expect($customerService->fresh()->is_free)->toBeTrue()
        ->and($cancelled->pluck('id')->all())->toBe([$upcomingCharge->id])
        ->and($upcomingCharge->status)->toBe(ChargeStatus::Cancelled)
        ->and($upcomingCharge->cancel_reason)->toBe('Hizmet ücretsiz işaretlendi.')
        ->and($currentCharge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('keeps the upcoming period charge when told not to cancel it', function () {
    $customerService = serviceToMarkFree();

    $this->travelTo('2027-02-15');

    $upcomingCharge = app(CreateRenewalCharge::class)->handle($customerService);

    expect(markFree($customerService, false))->toBeEmpty()
        ->and($customerService->fresh()->is_free)->toBeTrue()
        ->and($upcomingCharge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('does not cancel an upcoming charge with an allocation', function () {
    $customerService = serviceToMarkFree();

    $this->travelTo('2027-02-15');

    $charge = app(CreateRenewalCharge::class)->handle($customerService);

    app(RecordPayment::class)->handle($customerService->customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2027-02-15',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect(markFree($customerService))->toBeEmpty()
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('does nothing when the service is already free', function () {
    $customerService = serviceToMarkFree();
    $customerService->update(['is_free' => true]);

    $this->travelTo('2027-02-15');

    $charge = Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    expect(markFree($customerService))->toBeEmpty()
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('first creates and keeps the charge of a period that started without a scheduler run', function () {
    $customerService = serviceToMarkFree();

    $this->travelTo('2027-03-10');

    expect(markFree($customerService))->toBeEmpty();

    $charge = Charge::query()->sole();

    expect($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($customerService->fresh()->end_date->toDateString())->toBe('2028-03-01');
});

it('reopens the cancelled upcoming charge with the new price when the flag is removed before the period starts', function () {
    $customerService = serviceToMarkFree();

    $this->travelTo('2027-02-15');

    $upcomingCharge = app(CreateRenewalCharge::class)->handle($customerService);
    markFree($customerService);

    $this->travelTo('2027-02-20');

    $charge = app(RemoveCustomerServiceFreeFlag::class)->handle($customerService, 450000);

    expect($charge?->id)->toBe($upcomingCharge->id)
        ->and($charge->status)->toBe(ChargeStatus::Open)
        ->and($charge->amount)->toBe(450000)
        ->and($charge->cancelled_at)->toBeNull()
        ->and($charge->cancel_reason)->toBeNull()
        ->and(Charge::query()->count())->toBe(1);
});
