<?php

use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

it('does not allow two customers with the same short name', function (string $existing, string $duplicate) {
    Customer::factory()->create(['short_name' => $existing]);

    expect(fn () => Customer::factory()->create(['short_name' => $duplicate]))
        ->toThrow(UniqueConstraintViolationException::class);
})->with([
    'same spelling' => ['Logi', 'Logi'],
    'different case' => ['Logi', 'logi'],
    'Turkish characters' => ['Göksu', 'Goksu'],
]);

it('lists archived and not archived customers separately', function () {
    $active = Customer::factory()->create();
    $archived = Customer::factory()->archived()->create();

    expect(Customer::notArchived()->pluck('id')->all())->toBe([$active->id])
        ->and(Customer::onlyArchived()->pluck('id')->all())->toBe([$archived->id])
        ->and(Customer::count())->toBe(2);
});

it('archives and unarchives a customer', function () {
    $customer = Customer::factory()->create();

    expect($customer->isArchived())->toBeFalse();

    $customer->archive();

    expect($customer->fresh()->isArchived())->toBeTrue()
        ->and(Customer::notArchived()->count())->toBe(0);

    $customer->unarchive();

    expect($customer->fresh()->isArchived())->toBeFalse()
        ->and(Customer::notArchived()->count())->toBe(1);
});

it('keeps an archived customer reachable through its relations', function () {
    $customer = Customer::factory()->archived()->create();
    $contact = Contact::factory()->for($customer)->primary()->create();

    expect($contact->fresh()->customer->is($customer))->toBeTrue();
});

it('has contacts and a primary contact', function () {
    $customer = Customer::factory()->create();
    $primary = Contact::factory()->for($customer)->primary()->create();
    $other = Contact::factory()->for($customer)->create();
    Contact::factory()->primary()->create();

    expect($customer->contacts->modelKeys())->toEqualCanonicalizing([$primary->id, $other->id])
        ->and($customer->primaryContact->is($primary))->toBeTrue();
});

it('totals the open debt per currency from the remaining amounts of open charges', function () {
    $customer = Customer::factory()->create();
    $partial = Charge::factory()->for($customer)->create(['amount' => 400000]);
    PaymentAllocation::factory()->create(['charge_id' => $partial->id, 'amount' => 100000]);
    Charge::factory()->for($customer)->create(['amount' => 250000]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd]);
    Charge::factory()->for($customer)->paid()->create(['amount' => 900000]);
    Charge::factory()->for($customer)->cancelled()->create(['amount' => 700000, 'currency' => Currency::Eur]);
    Charge::factory()->create(['amount' => 800000]);

    $expected = [
        'TRY' => new Money(550000, Currency::Try),
        'USD' => new Money(5000, Currency::Usd),
    ];

    expect($customer->openChargeTotals())->toEqual($expected)
        ->and(Customer::query()->with(['charges' => fn ($charges) => $charges->withSum('allocations', 'amount')])->findOrFail($customer->id)->openChargeTotals())->toEqual($expected)
        ->and(Customer::factory()->create()->openChargeTotals())->toBe([]);
});

it('deletes the contacts of a deleted customer', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create();

    $customer->delete();

    expect(Contact::count())->toBe(0);
});
