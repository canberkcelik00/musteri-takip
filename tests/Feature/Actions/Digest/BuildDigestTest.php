<?php

use App\Actions\Charges\CreateManualCharge;
use App\Actions\Digest\BuildDigest;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Support\Settings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

/**
 * @return array{new_charges: mixed, free_decisions: mixed, overdue: mixed}
 */
function buildDigestBetween(string $since, string $now): array
{
    return app(BuildDigest::class)->handle(
        Carbon::parse($since, 'Europe/Istanbul'),
        Carbon::parse($now, 'Europe/Istanbul'),
    );
}

function chargeCreatedAt(string $moment, array $attributes = []): Charge
{
    test()->travelTo($moment);

    return Charge::factory()->create(['due_date' => '2026-12-01', ...$attributes]);
}

it('lists the charges created after the previous digest up to and including now', function () {
    chargeCreatedAt('2026-09-15 09:00:00'); // at the previous digest: it was in that one
    $first = chargeCreatedAt('2026-09-15 09:00:01');
    $last = chargeCreatedAt('2026-10-15 09:00:00'); // exactly now
    chargeCreatedAt('2026-10-15 09:00:01'); // after now: the next digest

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');

    expect($digest['new_charges']->pluck('id')->all())->toBe([$first->id, $last->id]);
});

it('lists every charge created during the month', function () {
    $charges = collect(['2026-09-20 10:00:00', '2026-10-01 00:05:00', '2026-10-14 23:59:59'])
        ->map(fn (string $moment): Charge => chargeCreatedAt($moment));

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');

    expect($digest['new_charges']->pluck('id')->all())->toBe($charges->pluck('id')->all());
});

it('compares the creation moments in the stored time zone', function () {
    $charge = chargeCreatedAt('2026-10-15 08:30:00');

    // 05:00 UTC is 08:00 in Istanbul and 06:00 UTC is 09:00: the charge is in between.
    $digest = app(BuildDigest::class)->handle(
        Carbon::parse('2026-10-15 05:00:00', 'UTC'),
        Carbon::parse('2026-10-15 06:00:00', 'UTC'),
    );

    expect($digest['new_charges']->pluck('id')->all())->toBe([$charge->id]);
});

it('leaves out cancelled charges and charges of archived customers', function () {
    $this->travelTo('2026-10-01 10:00:00');
    $kept = Charge::factory()->create(['due_date' => '2026-12-01']);
    Charge::factory()->cancelled()->create(['due_date' => '2026-12-01']);
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-12-01']);

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');

    expect($digest['new_charges']->pluck('id')->all())->toBe([$kept->id]);
});

it('marks the new charges closed fully or partly from the credit balance', function () {
    $this->travelTo('2026-10-01 10:00:00');
    $customer = Customer::factory()->create();
    $recordPayment = fn (int $amount) => app(RecordPayment::class)->handle($customer, [
        'amount' => $amount,
        'currency' => Currency::Try,
        'paid_on' => '2026-10-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);
    $createCharge = fn (int $amount, string $description) => app(CreateManualCharge::class)->handle($customer, [
        'description' => $description,
        'amount' => $amount,
        'currency' => Currency::Try,
        'due_date' => '2026-12-01',
    ]);

    $recordPayment(150000); // 1.500 TL credit balance
    $closed = $createCharge(100000, 'Alan adı'); // 1.000 TL from the balance: closed
    $partly = $createCharge(200000, 'Hosting'); // the remaining 500 TL from the balance
    $plain = $createCharge(40000, 'SSL'); // no balance left

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');
    $credit = $digest['new_charges']->mapWithKeys(fn (Charge $charge): array => [$charge->id => (int) $charge->credit_balance_amount]);

    expect($closed->refresh()->status)->toBe(ChargeStatus::Paid)
        ->and($credit->all())->toBe([$closed->id => 100000, $partly->id => 50000, $plain->id => 0]);
});

it('lists free services awaiting a decision, without cancelled services or archived customers', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $awaiting = CustomerService::factory()->free()->create(['start_date' => '2025-11-01', 'end_date' => '2026-11-01']);
    CustomerService::factory()->free()->create([
        'start_date' => '2025-11-02',
        'end_date' => '2026-11-02',
        'free_continuation_confirmed_for' => '2026-11-02',
    ]);
    CustomerService::factory()->free()->cancelled()->create(['start_date' => '2025-11-03', 'end_date' => '2026-11-03']);
    CustomerService::factory()->free()->for(Customer::factory()->archived())->create(['start_date' => '2025-11-04', 'end_date' => '2026-11-04']);
    CustomerService::factory()->free()->create(['start_date' => '2025-12-20', 'end_date' => '2026-12-20']); // outside the window

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');

    expect($digest['free_decisions']->pluck('id')->all())->toBe([$awaiting->id]);
});

it('lists overdue charges oldest due date first, with days and remaining amount, without archived customers', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $recent = Charge::factory()->create(['due_date' => '2026-10-14', 'amount' => 100000]);
    $oldest = Charge::factory()->create(['due_date' => '2026-09-01', 'amount' => 250000]);
    Charge::factory()->create(['due_date' => '2026-10-15']); // due today: not overdue yet
    Charge::factory()->paid()->create(['due_date' => '2026-09-01']);
    Charge::factory()->cancelled()->create(['due_date' => '2026-09-01']);
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-09-01']);

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');
    $overdue = $digest['overdue'];

    expect($overdue->pluck('id')->all())->toBe([$oldest->id, $recent->id])
        ->and($overdue->first()->daysOverdue())->toBe(44)
        ->and($overdue->first()->remainingAmount())->toBe(250000)
        ->and($overdue->last()->daysOverdue())->toBe(1);
});

it('keeps the overdue charges of a cancelled service', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $service = CustomerService::factory()->cancelled()->create(['start_date' => '2026-09-01', 'end_date' => '2027-09-01']);
    $charge = Charge::factory()->forCustomerService($service)->create();

    $digest = buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00');

    expect($digest['overdue']->pluck('id')->all())->toBe([$charge->id]);
});

it('is empty when every section is empty', function () {
    $this->travelTo('2026-10-15 09:00:00');

    expect(BuildDigest::isEmpty(buildDigestBetween('2026-09-15 09:00:00', '2026-10-15 09:00:00')))->toBeTrue();

    Charge::factory()->create(['due_date' => '2026-09-01']);

    expect(BuildDigest::isEmpty(buildDigestBetween('2026-10-15 09:00:00', '2026-10-15 09:00:00')))->toBeFalse();
});
