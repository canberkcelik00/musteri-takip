<?php

use App\Enums\SettingKey;
use App\Models\CustomerService;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

it('awaits a decision for a free, ending-soon service that has not been confirmed', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect($service->isAwaitingFreeDecision())->toBeTrue()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([$service->id]);
});

it('does not await a decision for a service ending after the lead time', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-07-16', 'end_date' => '2026-07-16']);

    expect($service->isAwaitingFreeDecision())->toBeFalse()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([]);
});

it('does not await a decision for a paid service', function () {
    $service = CustomerService::factory()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect($service->isAwaitingFreeDecision())->toBeFalse()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([]);
});

it('does not await a decision for a cancelled free service', function () {
    $service = CustomerService::factory()->free()->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect($service->isAwaitingFreeDecision())->toBeFalse()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([]);
});

it('stops awaiting a decision once confirmed for the current period', function () {
    $service = CustomerService::factory()->free()->create([
        'start_date' => '2025-06-20',
        'end_date' => '2026-06-20',
        'free_continuation_confirmed_for' => '2026-06-20',
    ]);

    expect($service->isAwaitingFreeDecision())->toBeFalse()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([]);
});

it('awaits a decision again once the period advances past a confirmed date', function () {
    $service = CustomerService::factory()->free()->create([
        'start_date' => '2025-06-20',
        'end_date' => '2026-06-20',
        'free_continuation_confirmed_for' => '2025-06-20',
    ]);

    expect($service->isAwaitingFreeDecision())->toBeTrue()
        ->and(CustomerService::query()->awaitingFreeDecision()->pluck('id')->all())->toBe([$service->id]);
});
