<?php

use App\Actions\Today\SummarizeToday;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

function summarizeToday(): array
{
    return app(SummarizeToday::class)->handle();
}

it('totals open debt per currency, keeping currencies separate', function () {
    Charge::factory()->create(['due_date' => '2026-06-20', 'amount' => 150000, 'currency' => Currency::Try]);
    Charge::factory()->create(['due_date' => '2026-06-25', 'amount' => 20000, 'currency' => Currency::Usd]);
    Charge::factory()->paid()->create(['due_date' => '2026-05-01', 'amount' => 500000]);

    $summary = summarizeToday();

    expect($summary['total'])->toEqual([
        'TRY' => new Money(150000, Currency::Try),
        'USD' => new Money(20000, Currency::Usd),
    ]);
});

it('separates the overdue total and count from the open total', function () {
    Charge::factory()->create(['due_date' => '2026-06-14', 'amount' => 100000]);
    Charge::factory()->create(['due_date' => '2026-06-20', 'amount' => 50000]);

    $summary = summarizeToday();

    expect($summary['overdue'])->toEqual(['TRY' => new Money(100000, Currency::Try)])
        ->and($summary['overdue_count'])->toBe(1);
});

it('leaves the overdue charges of archived customers out of the overdue total and count', function () {
    Charge::factory()->create(['due_date' => '2026-06-14', 'amount' => 100000]);
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-06-14', 'amount' => 70000]);

    $summary = summarizeToday();

    expect($summary['overdue'])->toEqual(['TRY' => new Money(100000, Currency::Try)])
        ->and($summary['overdue_count'])->toBe(1);
});

it('counts the remaining amount of a partially paid overdue charge', function () {
    $charge = Charge::factory()->create(['due_date' => '2026-06-01', 'amount' => 100000]);
    $payment = Payment::factory()->for($charge->customer)->create(['amount' => 40000, 'currency' => $charge->currency]);
    PaymentAllocation::factory()->for($payment)->for($charge)->create(['amount' => 40000]);

    $summary = summarizeToday();

    expect($summary['overdue'])->toEqual(['TRY' => new Money(60000, Currency::Try)])
        ->and($summary['total'])->toEqual(['TRY' => new Money(60000, Currency::Try)]);
});

it('totals charges due from today through the end of the month, excluding overdue and next month', function () {
    Charge::factory()->create(['due_date' => '2026-06-15', 'amount' => 30000]);
    Charge::factory()->create(['due_date' => '2026-06-14', 'amount' => 99999]);
    Charge::factory()->create(['due_date' => '2026-07-01', 'amount' => 88888]);

    $summary = summarizeToday();

    expect($summary['due_this_month'])->toEqual(['TRY' => new Money(30000, Currency::Try)]);
});

it('counts customers whose service is ending soon, including archived-excluded and free ones', function () {
    $renewing = Customer::factory()->create();
    CustomerService::factory()->for($renewing)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $archived = Customer::factory()->create();
    CustomerService::factory()->for($archived)->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $archived->archive();

    $summary = summarizeToday();

    expect($summary['renewal_customers_count'])->toBe(1);
});

it('includes an archived customer\'s open debt in the total, matching the open charges list', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => '2026-06-20', 'amount' => 75000]);
    $customer->archive();

    $summary = summarizeToday();

    expect($summary['total'])->toEqual(['TRY' => new Money(75000, Currency::Try)]);
});
