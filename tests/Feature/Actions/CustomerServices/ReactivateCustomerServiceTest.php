<?php

use App\Actions\CustomerServices\CancelCustomerService;
use App\Actions\CustomerServices\CreateRenewalCharge;
use App\Actions\CustomerServices\ReactivateCustomerService;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\InvalidCustomerService;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * A 4.000 TL service for the period 01.03.2026 – 01.03.2027, cancelled on the given day
 * together with its upcoming period charge. The renewal charge of 01.03.2027 is created
 * first when the renewal window (from 30.01.2027) has started by then.
 */
function cancelledServiceOn(string $cancelledOn): CustomerService
{
    $customerService = CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    test()->travelTo($cancelledOn);

    app(CreateRenewalCharge::class)->handle($customerService);
    app(CancelCustomerService::class)->handle($customerService, cancelNote: 'Müşteri vazgeçti.');

    return $customerService->refresh();
}

/**
 * @param  array{start_date?: string, create_first_charge?: bool, reopen_upcoming_charge?: bool}  $attributes
 * @return Collection<int, Charge>
 */
function reactivate(CustomerService $customerService, array $attributes = []): Collection
{
    return app(ReactivateCustomerService::class)->handle($customerService, $attributes);
}

function addCreditBalance(CustomerService $customerService, int $amount, string $paidOn): void
{
    app(RecordPayment::class)->handle(actor(), $customerService->customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => $paidOn,
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
}

it('reactivates a service whose period has not ended and keeps its dates', function () {
    $customerService = cancelledServiceOn('2026-06-15');

    $this->travelTo('2026-07-01');

    expect(reactivate($customerService))->toBeEmpty();

    $customerService->refresh();

    expect($customerService->status)->toBe(CustomerServiceStatus::Active)
        ->and($customerService->cancelled_on)->toBeNull()
        ->and($customerService->cancel_note)->toBeNull()
        ->and($customerService->start_date->toDateString())->toBe('2026-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01');
});

it('reopens the upcoming period charge cancelled with the service by default', function () {
    $customerService = cancelledServiceOn('2027-02-15');
    $charge = Charge::query()->sole();

    expect($charge->status)->toBe(ChargeStatus::Cancelled);

    $this->travelTo('2027-02-20');

    $reopened = reactivate($customerService)->sole();

    expect($reopened->id)->toBe($charge->id)
        ->and($reopened->status)->toBe(ChargeStatus::Open)
        ->and($reopened->amount)->toBe(400000)
        ->and($reopened->cancelled_at)->toBeNull()
        ->and($reopened->cancel_reason)->toBeNull()
        ->and(Charge::query()->count())->toBe(1);
});

it('applies the credit balance to the reopened charge', function () {
    $customerService = cancelledServiceOn('2027-02-15');

    addCreditBalance($customerService, 100000, '2027-02-15');

    $this->travelTo('2027-02-20');

    expect(reactivate($customerService)->sole()->remainingAmount())->toBe(300000)
        ->and($customerService->customer->creditBalances())->toBe([]);
});

it('keeps the upcoming period charge cancelled when told not to reopen it and creates no second charge', function () {
    $customerService = cancelledServiceOn('2027-02-15');

    $this->travelTo('2027-02-20');

    expect(reactivate($customerService, ['reopen_upcoming_charge' => false]))->toBeEmpty()
        ->and($customerService->fresh()->status)->toBe(CustomerServiceStatus::Active)
        ->and(Charge::query()->sole()->status)->toBe(ChargeStatus::Cancelled);
});

it('creates the renewal charge at once when reactivated within the renewal window', function () {
    $customerService = cancelledServiceOn('2026-06-15');

    $this->travelTo('2027-02-15');

    $charge = reactivate($customerService)->sole();

    expect($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->period_start->toDateString())->toBe('2027-03-01')
        ->and($charge->status)->toBe(ChargeStatus::Open);
});

it('restarts a service whose period ended while cancelled from the new start date', function () {
    $customerService = cancelledServiceOn('2026-06-15');

    $this->travelTo('2027-06-01');

    addCreditBalance($customerService, 100000, '2027-06-01');

    $charge = reactivate($customerService, ['start_date' => '2027-06-01'])->sole();

    $customerService->refresh();

    expect($customerService->status)->toBe(CustomerServiceStatus::Active)
        ->and($customerService->start_date->toDateString())->toBe('2027-06-01')
        ->and($customerService->end_date->toDateString())->toBe('2028-06-01')
        ->and($charge->customer_id)->toBe($customerService->customer_id)
        ->and($charge->customer_service_id)->toBe($customerService->id)
        ->and($charge->description)->toBe('Kurumsal Paket 2027–2028')
        ->and($charge->amount)->toBe(400000)
        ->and($charge->due_date->toDateString())->toBe('2027-06-01')
        ->and($charge->period_start->toDateString())->toBe('2027-06-01')
        ->and($charge->period_end->toDateString())->toBe('2028-06-01')
        ->and($charge->remainingAmount())->toBe(300000)
        ->and(Charge::query()->count())->toBe(1);
});

it('creates no first charge when told not to', function () {
    $customerService = cancelledServiceOn('2026-06-15');

    $this->travelTo('2027-06-01');

    expect(reactivate($customerService, ['start_date' => '2027-06-01', 'create_first_charge' => false]))->toBeEmpty()
        ->and($customerService->fresh()->status)->toBe(CustomerServiceStatus::Active)
        ->and(Charge::query()->count())->toBe(0);
});

it('creates no charge when restarting a free service', function () {
    $customerService = cancelledServiceOn('2026-06-15');
    $customerService->update(['is_free' => true]);

    $this->travelTo('2027-06-01');

    expect(reactivate($customerService, ['start_date' => '2027-06-01']))->toBeEmpty()
        ->and(Charge::query()->count())->toBe(0);
});

it('does not reopen a cancelled charge of a free service', function () {
    $customerService = cancelledServiceOn('2027-02-15');
    $customerService->update(['is_free' => true]);

    $this->travelTo('2027-02-20');

    expect(reactivate($customerService))->toBeEmpty()
        ->and(Charge::query()->sole()->status)->toBe(ChargeStatus::Cancelled);
});

it('reopens the cancelled charge when restarting from the previous end date', function () {
    $customerService = cancelledServiceOn('2027-02-15');
    $charge = Charge::query()->sole();

    $this->travelTo('2027-06-01');

    $reopened = reactivate($customerService, ['start_date' => '2027-03-01'])->sole();

    expect($reopened->id)->toBe($charge->id)
        ->and($reopened->status)->toBe(ChargeStatus::Open)
        ->and($customerService->fresh()->end_date->toDateString())->toBe('2028-03-01')
        ->and(Charge::query()->count())->toBe(1);
});

it('keeps the cancelled charge and creates none when restarting from the previous end date without reopening', function () {
    $customerService = cancelledServiceOn('2027-02-15');

    $this->travelTo('2027-06-01');

    expect(reactivate($customerService, ['start_date' => '2027-03-01', 'reopen_upcoming_charge' => false]))->toBeEmpty()
        ->and(Charge::query()->sole()->status)->toBe(ChargeStatus::Cancelled);
});

it('rejects an invalid start date for a service whose period ended and changes nothing', function (string $today, ?string $startDate, string $message) {
    $customerService = cancelledServiceOn('2026-06-15');

    $this->travelTo($today);

    $attributes = $startDate === null ? [] : ['start_date' => $startDate];

    expect(fn () => reactivate($customerService, $attributes))->toThrow(InvalidCustomerService::class, $message);

    $customerService->refresh();

    expect($customerService->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($customerService->cancelled_on?->toDateString())->toBe('2026-06-15')
        ->and($customerService->start_date->toDateString())->toBe('2026-03-01')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01')
        ->and(Charge::query()->count())->toBe(0);
})->with([
    'missing' => ['2027-06-01', null, 'Dönemi sona ermiş hizmeti yeniden aktif etmek için yeni başlangıç tarihi girin.'],
    'before the previous end date' => ['2027-06-01', '2027-02-28', 'Yeni başlangıç tarihi önceki dönemin bitişinden önce olamaz.'],
    'period already ended' => ['2028-06-01', '2027-03-01', 'Başlangıç tarihi olarak hizmetin şu anki döneminin başlangıcını girin.'],
]);

it('rejects reactivating a service of an archived customer and changes nothing', function (string $today, array $attributes) {
    $customerService = cancelledServiceOn('2027-02-15');
    $customerService->customer->archive();

    $this->travelTo($today);

    expect(fn () => reactivate($customerService, $attributes))
        ->toThrow(InvalidCustomerService::class, 'Önce müşteriyi arşivden çıkarın.');

    $customerService->refresh();

    expect($customerService->status)->toBe(CustomerServiceStatus::Cancelled)
        ->and($customerService->cancel_note)->toBe('Müşteri vazgeçti.')
        ->and($customerService->end_date->toDateString())->toBe('2027-03-01')
        ->and(Charge::query()->sole()->status)->toBe(ChargeStatus::Cancelled);
})->with([
    'period not ended' => ['2027-02-20', []],
    'period ended' => ['2027-06-01', ['start_date' => '2027-06-01']],
]);

it('does nothing when the service is active', function () {
    $customerService = CustomerService::factory()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    $this->travelTo('2026-06-15');

    expect(reactivate($customerService, ['start_date' => '2026-06-15']))->toBeEmpty()
        ->and($customerService->fresh()->start_date->toDateString())->toBe('2026-03-01');
});
