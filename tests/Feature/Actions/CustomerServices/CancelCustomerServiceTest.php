<?php

use App\Actions\CustomerServices\AdvanceDueCustomerServicePeriods;
use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * An active 4.000 TL service for the period 01.03.2026 – 01.03.2027; its renewal window
 * starts on 30.01.2027.
 */
function serviceToCancel(): CustomerService
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
function cancelService(CustomerService $customerService, ?string $cancelledOn = null, ?string $cancelNote = null, bool $cancelUpcomingCharges = true): Collection
{
    return app(CancelCustomerService::class)->handle($customerService, $cancelledOn, $cancelNote, $cancelUpcomingCharges);
}

function payForCustomerOf(CustomerService $customerService, int $amount, string $paidOn): void
{
    app(RecordPayment::class)->handle(actor(), $customerService->customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => $paidOn,
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

it('cancels the service with the given date and note', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2026-06-15');

    cancelService($customerService, '2026-06-10', 'Müşteri siteyi kapattı.');

    $customerService->refresh();

    expect($customerService->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($customerService->cancelled_on->toDateString())->toBe('2026-06-10')
        ->and($customerService->cancel_note)->toBe('Müşteri siteyi kapattı.')
        ->and($customerService->start_date->toDateString())->toBe('2026-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01');
});

it('uses today as the cancellation date when none is given', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2026-06-15 23:30:00');

    cancelService($customerService);

    expect($customerService->fresh()->cancelled_on->toDateString())->toBe('2026-06-15');
});

it('cancels the unpaid charge of the upcoming period by default and keeps the current period charge', function () {
    $customerService = serviceToCancel();
    $currentCharge = Charge::factory()->forCustomerService($customerService)->create();

    $this->travelTo('2027-02-15');

    $upcomingCharge = app(CreateRenewalCharge::class)->handle($customerService);

    $cancelled = cancelService($customerService);

    $upcomingCharge->refresh();

    expect($cancelled->pluck('id')->all())->toBe([$upcomingCharge->id])
        ->and($upcomingCharge->status)->toBe(ChargeStatus::Cancelled)
        ->and($upcomingCharge->cancel_reason)->toBe('Hizmet iptal edildi.')
        ->and($upcomingCharge->cancelled_at)->not->toBeNull()
        ->and($currentCharge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($currentCharge->fresh()->isOverdue())->toBeTrue();
});

it('keeps the upcoming period charge when told not to cancel it', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2027-02-15');

    $upcomingCharge = app(CreateRenewalCharge::class)->handle($customerService);

    expect(cancelService($customerService, cancelUpcomingCharges: false))->toBeEmpty()
        ->and($upcomingCharge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($customerService->fresh()->status)->toBe(CustomerServiceStatus::Cancelled);
});

it('never cancels a charge of a period that has already started', function (bool $cancelUpcomingCharges) {
    $customerService = serviceToCancel();
    $pastCharge = Charge::factory()->forCustomerService($customerService)->create();

    $this->travelTo('2026-12-01');

    cancelService($customerService, cancelUpcomingCharges: $cancelUpcomingCharges);

    expect($pastCharge->fresh()->status)->toBe(ChargeStatus::Open);
})->with([true, false]);

it('counts a period starting today as started and does not cancel its charge', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2027-02-15');

    $charge = app(CreateRenewalCharge::class)->handle($customerService);

    $this->travelTo('2027-03-01 00:00:00');

    expect(cancelService($customerService))->toBeEmpty()
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('does not cancel an upcoming charge partly paid by a payment', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2027-02-15');

    $charge = app(CreateRenewalCharge::class)->handle($customerService);
    payForCustomerOf($customerService, 100000, '2027-02-15');

    expect(cancelService($customerService))->toBeEmpty()
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($charge->fresh()->remainingAmount())->toBe(300000);
});

it('does not cancel an upcoming charge covered from the credit balance', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2027-02-15');

    payForCustomerOf($customerService, 400000, '2027-02-15');
    $charge = app(CreateRenewalCharge::class)->handle($customerService);

    expect($charge->status)->toBe(ChargeStatus::Paid)
        ->and(cancelService($customerService))->toBeEmpty()
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Paid);
});

it('stops renewal charges and period advancement after cancellation', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2026-06-15');

    cancelService($customerService);

    $this->travelTo('2027-02-15');

    expect(app(CreateDueRenewalCharges::class)->handle())->toBeEmpty();

    $this->travelTo('2027-03-10');

    expect(app(AdvanceDueCustomerServicePeriods::class)->handle())->toBeEmpty()
        ->and($customerService->fresh()->end_date->toDateString())->toBe('2027-03-01')
        ->and(Charge::query()->count())->toBe(0);
});

it('does nothing when called on a cancelled service', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2026-06-15');

    cancelService($customerService, '2026-06-15', 'İlk not');

    $this->travelTo('2027-02-15');

    $charge = Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
    ]);

    expect(cancelService($customerService, '2027-02-15', 'İkinci not'))->toBeEmpty()
        ->and($customerService->fresh()->cancelled_on->toDateString())->toBe('2026-06-15')
        ->and($customerService->fresh()->cancel_note)->toBe('İlk not')
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
});

it('first creates the missing charge of a period that started without a scheduler run and keeps it', function () {
    $customerService = serviceToCancel();

    $this->travelTo('2027-03-10');

    expect(cancelService($customerService))->toBeEmpty();

    $customerService->refresh();
    $charge = Charge::query()->sole();

    expect($customerService->start_date->toDateString())->toBe('2027-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2028-03-01')
        ->and($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open);
});
