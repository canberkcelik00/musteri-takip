<?php

use App\Actions\CustomerServices\PreviewCustomerServiceCancellation;
use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;

/**
 * An active 4.000 TL service for 01.03.2026 – 01.03.2027 with today set to 15.06.2026.
 */
function serviceToPreview(): CustomerService
{
    test()->travelTo('2026-06-15 10:00:00');

    return CustomerService::factory()->create([
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);
}

function upcomingCharge(CustomerService $customerService, array $attributes = []): Charge
{
    return Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
        ...$attributes,
    ]);
}

it('lists the unpaid charge of the upcoming period as cancellable', function () {
    $customerService = serviceToPreview();
    $upcoming = upcomingCharge($customerService);

    $preview = app(PreviewCustomerServiceCancellation::class)->handle($customerService);

    expect($preview['cancellable']->modelKeys())->toBe([$upcoming->id])
        ->and($preview['overdue'])->toBeEmpty()
        ->and($preview['open'])->toBeEmpty();
});

it('keeps the charge of a period that has started and overdue charges out of the cancellable ones', function () {
    $customerService = serviceToPreview();
    $started = Charge::factory()->forCustomerService($customerService)->create(['amount' => 150000]);

    $preview = app(PreviewCustomerServiceCancellation::class)->handle($customerService);

    expect($preview['cancellable'])->toBeEmpty()
        ->and($preview['overdue']->modelKeys())->toBe([$started->id])
        ->and($preview['open'])->toBeEmpty();
});

it('puts a charge that is not yet due into the open group', function () {
    $customerService = serviceToPreview();
    $notDue = Charge::factory()->forCustomerService($customerService)->create([
        'due_date' => '2026-07-01',
        'period_start' => '2026-03-01',
    ]);

    $preview = app(PreviewCustomerServiceCancellation::class)->handle($customerService);

    expect($preview['open']->modelKeys())->toBe([$notDue->id])
        ->and($preview['overdue'])->toBeEmpty();
});

it('keeps an upcoming charge with an allocation among those that stay', function () {
    $customerService = serviceToPreview();
    $upcoming = upcomingCharge($customerService);

    app(RecordPayment::class)->handle($customerService->customer, [
        'amount' => 100000,
        'currency' => Currency::Try,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    $preview = app(PreviewCustomerServiceCancellation::class)->handle($customerService);

    expect($preview['cancellable'])->toBeEmpty()
        ->and($preview['open']->modelKeys())->toBe([$upcoming->id]);
});

it('ignores cancelled and paid charges', function () {
    $customerService = serviceToPreview();
    upcomingCharge($customerService, ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'x']);
    Charge::factory()->forCustomerService($customerService)->paid()->create();

    $preview = app(PreviewCustomerServiceCancellation::class)->handle($customerService);

    expect($preview['cancellable'])->toBeEmpty()
        ->and($preview['overdue'])->toBeEmpty()
        ->and($preview['open'])->toBeEmpty();
});
