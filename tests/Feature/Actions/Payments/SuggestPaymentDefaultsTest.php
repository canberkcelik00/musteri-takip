<?php

use App\Actions\Payments\SuggestPaymentDefaults;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;

function paymentDefaultsFor(?Customer $customer): array
{
    return app(SuggestPaymentDefaults::class)->handle($customer);
}

it('takes the currency of the only open charge', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['currency' => Currency::Usd]);

    expect(paymentDefaultsFor($customer)['currency'])->toBe(Currency::Usd);
});

it('takes the currency of the oldest open charge when there are several currencies', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['currency' => Currency::Try, 'due_date' => '2026-09-01']);
    Charge::factory()->for($customer)->create(['currency' => Currency::Eur, 'due_date' => '2026-03-01']);
    Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'due_date' => '2026-06-01']);

    expect(paymentDefaultsFor($customer)['currency'])->toBe(Currency::Eur);
});

it('uses TL when the customer has no open charge', function () {
    $customer = Customer::factory()->create();

    expect(paymentDefaultsFor($customer)['currency'])->toBe(Currency::Try)
        ->and(paymentDefaultsFor(null)['currency'])->toBe(Currency::Try);
});

it('ignores paid, cancelled and other customers\' charges for the currency', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['currency' => Currency::Eur, 'due_date' => '2026-01-01', 'status' => ChargeStatus::Paid]);
    Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'due_date' => '2026-02-01', 'status' => ChargeStatus::Cancelled]);
    Charge::factory()->create(['currency' => Currency::Usd, 'due_date' => '2026-03-01']);

    expect(paymentDefaultsFor($customer)['currency'])->toBe(Currency::Try);
});

it('takes the method of the customer\'s latest payment', function () {
    $customer = Customer::factory()->create();
    $transfer = PaymentMethod::query()->where('name', 'Havale/EFT')->firstOrFail();
    $card = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    Payment::factory()->for($customer)->create(['paid_on' => '2026-05-01', 'payment_method_id' => $transfer->id]);
    Payment::factory()->for($customer)->create(['paid_on' => '2026-06-01', 'payment_method_id' => $card->id]);

    expect(paymentDefaultsFor($customer)['payment_method_id'])->toBe($card->id);
});

it('does not take the method of another customer\'s payment', function () {
    $customer = Customer::factory()->create();
    $card = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    Payment::factory()->create(['payment_method_id' => $card->id]);

    $first = PaymentMethod::query()->orderBy('sort_order')->firstOrFail();

    expect(paymentDefaultsFor($customer)['payment_method_id'])->toBe($first->id)
        ->and(paymentDefaultsFor(null)['payment_method_id'])->toBe($first->id);
});

it('falls back to the first method in the list order when the latest method is archived', function () {
    $customer = Customer::factory()->create();
    $archived = PaymentMethod::factory()->archived()->create(['sort_order' => 0]);
    Payment::factory()->for($customer)->create(['payment_method_id' => $archived->id]);

    $first = PaymentMethod::query()->notArchived()->orderBy('sort_order')->firstOrFail();

    expect(paymentDefaultsFor($customer)['payment_method_id'])->toBe($first->id)
        ->not->toBe($archived->id);
});

it('does not count a fully allocated charge as open debt for the currency', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['currency' => Currency::Usd, 'amount' => 5000, 'due_date' => '2026-01-01']);
    PaymentAllocation::factory()->create([
        'charge_id' => $charge->id,
        'payment_id' => Payment::factory()->for($customer)->create(['currency' => Currency::Usd, 'amount' => 5000])->id,
        'amount' => 5000,
    ]);

    expect(paymentDefaultsFor($customer)['currency'])->toBe(Currency::Try);
});
