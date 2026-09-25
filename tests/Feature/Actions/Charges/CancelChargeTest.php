<?php

use App\Actions\Charges\CancelCharge;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;

function cancelCharge(Charge $charge, string $reason): Charge
{
    return app(CancelCharge::class)->handle($charge, $reason);
}

it('cancels the charge with a reason', function () {
    $this->travelTo('2027-02-10 10:00:00');
    $charge = Charge::factory()->create();

    $cancelled = cancelCharge($charge, '  Müşteriyle anlaşıldı, alınmayacak.  ');

    expect($cancelled->status)->toBe(ChargeStatus::Cancelled)
        ->and($cancelled->cancel_reason)->toBe('Müşteriyle anlaşıldı, alınmayacak.')
        ->and($cancelled->cancelled_at->toDateTimeString())->toBe('2027-02-10 10:00:00')
        ->and($charge->fresh()->status)->toBe(ChargeStatus::Cancelled);
});

it('does not cancel a charge without a reason', function (string $reason) {
    $charge = Charge::factory()->create();

    expect(fn () => cancelCharge($charge, $reason))
        ->toThrow(InvalidCharge::class, __('charges.cancel_reason_required'));

    expect($charge->fresh()->status)->toBe(ChargeStatus::Open)
        ->and($charge->fresh()->cancelled_at)->toBeNull();
})->with(['', '   ']);

it('does not cancel a charge with allocations', function (int $paid, ChargeStatus $status) {
    $charge = Charge::factory()->create(['amount' => 400000]);
    app(RecordPayment::class)->handle($charge->customer, [
        'amount' => $paid,
        'currency' => Currency::Try,
        'paid_on' => '2027-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect(fn () => cancelCharge($charge, 'Yanlış borç'))
        ->toThrow(InvalidCharge::class, __('charges.cannot_cancel_with_allocations'));

    expect($charge->fresh()->status)->toBe($status);
})->with([
    'partially paid' => [100000, ChargeStatus::Open],
    'paid' => [400000, ChargeStatus::Paid],
]);

it('leaves an already cancelled charge untouched', function () {
    $charge = Charge::factory()->cancelled()->create(['cancel_reason' => 'İlk gerekçe']);

    cancelCharge($charge, 'İkinci gerekçe');

    expect($charge->fresh()->cancel_reason)->toBe('İlk gerekçe');
});

it('does not create the charge of a cancelled period again on renewal', function () {
    $customerService = CustomerService::factory()->create([
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);
    $this->travelTo('2027-02-01 10:00:00');
    $charge = app(CreateRenewalCharge::class)->handle($customerService);

    cancelCharge($charge, 'Bu dönem alınmayacak.');

    expect(app(CreateRenewalCharge::class)->handle($customerService->fresh()))->toBeNull()
        ->and($customerService->charges()->count())->toBe(1);
});
