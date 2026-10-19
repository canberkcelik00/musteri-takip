<?php

use App\Actions\PaymentMethods\ArchivePaymentMethod;
use App\Exceptions\LastActivePaymentMethod;
use App\Models\PaymentMethod;

it('archives a method while another one stays active', function () {
    $method = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();

    app(ArchivePaymentMethod::class)->handle($method);

    expect($method->refresh()->isArchived())->toBeTrue()
        ->and(PaymentMethod::query()->notArchived()->count())->toBe(1);
});

it('does not archive the last active method and says why', function () {
    PaymentMethod::query()->where('name', '!=', 'Kredi Kartı')->get()->each->archive();
    $method = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();

    expect(fn () => app(ArchivePaymentMethod::class)->handle($method))
        ->toThrow(LastActivePaymentMethod::class, 'En az bir ödeme yöntemi aktif kalmalı. Önce başka bir yöntem ekleyin veya arşivden çıkarın.');

    expect($method->refresh()->isArchived())->toBeFalse();
});

it('leaves an archived method as it is', function () {
    $method = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    $method->archive();
    $archivedAt = $method->refresh()->archived_at;

    $this->travel(1)->hours();
    app(ArchivePaymentMethod::class)->handle($method);

    expect($method->refresh()->archived_at->equalTo($archivedAt))->toBeTrue();
});

it('archives a method again once another one is active', function () {
    PaymentMethod::query()->where('name', '!=', 'Kredi Kartı')->get()->each->archive();
    $method = PaymentMethod::query()->where('name', 'Kredi Kartı')->firstOrFail();
    $new = PaymentMethod::factory()->create();

    app(ArchivePaymentMethod::class)->handle($method);

    expect($method->refresh()->isArchived())->toBeTrue()
        ->and($new->refresh()->isArchived())->toBeFalse();
});
