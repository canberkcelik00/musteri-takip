<?php

use App\Models\PaymentMethod;

it('has the default payment methods in order', function () {
    expect(PaymentMethod::orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Havale/EFT', 'Nakit', 'Kredi Kartı']);
});

it('lists archived and not archived payment methods separately', function () {
    PaymentMethod::query()->delete();
    $active = PaymentMethod::factory()->create();
    $archived = PaymentMethod::factory()->archived()->create();

    expect(PaymentMethod::notArchived()->pluck('id')->all())->toBe([$active->id])
        ->and(PaymentMethod::onlyArchived()->pluck('id')->all())->toBe([$archived->id]);
});
