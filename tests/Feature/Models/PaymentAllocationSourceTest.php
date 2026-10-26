<?php

use App\Actions\Charges\CreateCharge;
use App\Actions\Payments\RecordPayment;
use App\Enums\AllocationSource;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;

function payFromBalanceAt(string $now, Customer $customer, int $amount): Charge
{
    test()->travelTo(Carbon::parse($now, 'Europe/Istanbul'));

    return app(CreateCharge::class)->handle($customer, [
        'description' => 'Alan adı',
        'amount' => $amount,
        'currency' => Currency::Try,
        'due_date' => '2027-03-01',
    ]);
}

it('marks allocations made while recording a payment as from the payment', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 400000]);

    $payment = app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 400000,
        'currency' => Currency::Try,
        'paid_on' => '2026-03-10',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    expect($payment->allocations->pluck('source')->all())->toBe([AllocationSource::Payment]);
});

it('finds the allocations applied from the credit balance on a day in Istanbul time', function () {
    $customer = Customer::factory()->create();
    $this->travelTo(Carbon::parse('2027-01-28 12:00', 'Europe/Istanbul'));
    app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 1000000,
        'currency' => Currency::Try,
        'paid_on' => '2027-01-28',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    payFromBalanceAt('2027-01-29 23:59:59', $customer, 10000);
    $first = payFromBalanceAt('2027-01-30 00:00:00', $customer, 20000);
    $second = payFromBalanceAt('2027-01-30 23:59:59', $customer, 30000);
    payFromBalanceAt('2027-01-31 00:00:00', $customer, 40000);

    $this->travelTo(Carbon::parse('2027-01-30 12:00', 'Europe/Istanbul'));
    $paymentToday = Charge::factory()->for($customer)->create(['amount' => 50000]);
    app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 50000,
        'currency' => Currency::Try,
        'paid_on' => '2027-01-30',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ], [$paymentToday->id => 50000]);

    expect(PaymentAllocation::query()->appliedFromCreditBalanceOn(today())->orderBy('id')->pluck('charge_id')->all())
        ->toBe([$first->id, $second->id]);
});
