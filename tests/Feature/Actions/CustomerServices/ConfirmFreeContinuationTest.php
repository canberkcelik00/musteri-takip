<?php

use App\Actions\CustomerServices\ConfirmFreeContinuation;
use App\Models\CustomerService;

function confirmFreeContinuation(CustomerService $customerService): void
{
    app(ConfirmFreeContinuation::class)->handle($customerService);
}

it('stores the current end date as confirmed', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    confirmFreeContinuation($service);

    expect($service->fresh()->free_continuation_confirmed_for->toDateString())->toBe('2026-06-20')
        ->and($service->fresh()->isAwaitingFreeDecision())->toBeFalse();
});

it('does nothing when called again', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    confirmFreeContinuation($service);
    confirmFreeContinuation($service->fresh());

    expect($service->fresh()->free_continuation_confirmed_for->toDateString())->toBe('2026-06-20');
});

it('does nothing for a paid service', function () {
    $service = CustomerService::factory()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    confirmFreeContinuation($service);

    expect($service->fresh()->free_continuation_confirmed_for)->toBeNull();
});

it('does nothing for a cancelled free service', function () {
    $service = CustomerService::factory()->free()->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    confirmFreeContinuation($service);

    expect($service->fresh()->free_continuation_confirmed_for)->toBeNull();
});
