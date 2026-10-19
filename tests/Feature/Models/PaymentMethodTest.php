<?php

use App\Models\PaymentMethod;

it('has the default payment methods in order', function () {
    expect(PaymentMethod::orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Havale/EFT', 'Kredi Kartı']);
});

it('puts a new method without a sort order at the end of the list', function () {
    $new = PaymentMethod::create(['name' => 'Çek']);

    expect($new->refresh()->sort_order)->toBe(4);
});

it('keeps a given sort order', function () {
    $new = PaymentMethod::create(['name' => 'Çek', 'sort_order' => 0]);

    expect($new->refresh()->sort_order)->toBe(0);
});

it('starts the sort order at 1 when there is no method yet', function () {
    PaymentMethod::query()->delete();

    expect(PaymentMethod::create(['name' => 'Çek'])->refresh()->sort_order)->toBe(1);
});

it('lists archived and not archived payment methods separately', function () {
    PaymentMethod::query()->delete();
    $active = PaymentMethod::factory()->create();
    $archived = PaymentMethod::factory()->archived()->create();

    expect(PaymentMethod::notArchived()->pluck('id')->all())->toBe([$active->id])
        ->and(PaymentMethod::onlyArchived()->pluck('id')->all())->toBe([$archived->id]);
});
