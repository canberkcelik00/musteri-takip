<?php

use App\Actions\PaymentMethods\ArchivePaymentMethod;
use App\Exceptions\LastActivePaymentMethod;
use App\Models\PaymentMethod;

it('archives a method while another one stays active', function () {
    $cash = PaymentMethod::query()->where('name', 'Nakit')->firstOrFail();

    app(ArchivePaymentMethod::class)->handle($cash);

    expect($cash->refresh()->isArchived())->toBeTrue()
        ->and(PaymentMethod::query()->notArchived()->count())->toBe(2);
});

it('does not archive the last active method and says why', function () {
    PaymentMethod::query()->where('name', '!=', 'Nakit')->get()->each->archive();
    $cash = PaymentMethod::query()->where('name', 'Nakit')->firstOrFail();

    expect(fn () => app(ArchivePaymentMethod::class)->handle($cash))
        ->toThrow(LastActivePaymentMethod::class, 'En az bir ödeme yöntemi aktif kalmalı. Önce başka bir yöntem ekleyin veya arşivden çıkarın.');

    expect($cash->refresh()->isArchived())->toBeFalse();
});

it('leaves an archived method as it is', function () {
    $cash = PaymentMethod::query()->where('name', 'Nakit')->firstOrFail();
    $cash->archive();
    $archivedAt = $cash->refresh()->archived_at;

    $this->travel(1)->hours();
    app(ArchivePaymentMethod::class)->handle($cash);

    expect($cash->refresh()->archived_at->equalTo($archivedAt))->toBeTrue();
});

it('archives a method again once another one is active', function () {
    PaymentMethod::query()->where('name', '!=', 'Nakit')->get()->each->archive();
    $cash = PaymentMethod::query()->where('name', 'Nakit')->firstOrFail();
    $new = PaymentMethod::factory()->create();

    app(ArchivePaymentMethod::class)->handle($cash);

    expect($cash->refresh()->isArchived())->toBeTrue()
        ->and($new->refresh()->isArchived())->toBeFalse();
});
