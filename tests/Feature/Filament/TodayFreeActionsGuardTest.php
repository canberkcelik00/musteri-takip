<?php

use App\Enums\CustomerServiceStatus;
use App\Filament\Widgets\TodayTasks;
use App\Models\CustomerService;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * The "Bugün" free-decision row actions take the service from a client-supplied `customerService`
 * argument (G-5.7 hands them the services the page rendered, to avoid a query per row). Whatever
 * the client sends, and however the service changed since the row was drawn, the action works on
 * the current database row.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

function awaitingFreeService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->free()->create([
        'start_date' => '2025-06-20',
        'end_date' => '2026-06-20',
        'price' => 400000,
        ...$attributes,
    ]);
}

it('confirms nothing when the service was cancelled in another tab after the row was drawn', function () {
    $service = awaitingFreeService();

    $widget = Livewire::test(TodayTasks::class)
        ->mountAction('confirmFreeContinuation', ['customerService' => $service->id]);

    $service->update(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-06-15']);

    $widget->callMountedAction()->assertHasNoErrors();

    expect($service->fresh()->free_continuation_confirmed_for)->toBeNull();
});

it('creates no second charge when the service was made paid in another tab after the row was drawn', function () {
    $service = awaitingFreeService();

    $widget = Livewire::test(TodayTasks::class)
        ->mountAction('removeFree', ['customerService' => $service->id])
        ->fillForm(['price' => '9.000']);

    // The other tab made the service paid at its own price, which created the upcoming charge.
    Livewire::test(TodayTasks::class)
        ->callAction('removeFree', ['price' => '4.000'], ['customerService' => $service->id]);

    $widget->callMountedAction()->assertHasNoErrors();

    $service->refresh();

    expect($service->is_free)->toBeFalse()
        ->and($service->price)->toBe(400000)
        ->and($service->charges()->count())->toBe(1);
});

it('leaves a service alone when the id sent is not a service of the list', function (string $case) {
    $service = match ($case) {
        'unknown id' => null,
        'paid service' => CustomerService::factory()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']),
        'cancelled free service' => awaitingFreeService(['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2026-01-01']),
    };
    $id = $service?->id ?? 999999;

    foreach (['confirmFreeContinuation', 'removeFree'] as $action) {
        try {
            Livewire::test(TodayTasks::class)
                ->mountAction($action, ['customerService' => $id])
                ->callMountedAction();
        } catch (TypeError $error) {
            // An id without a service ends in a server error rather than a refusal (reported in G-5.7);
            // no other case may throw.
            expect($service)->toBeNull();
        }
    }

    if ($service !== null) {
        $service->refresh();

        expect($service->free_continuation_confirmed_for)->toBeNull()
            ->and($service->is_free)->toBe($case === 'cancelled free service')
            ->and($service->charges()->count())->toBe(0);
    }

    expect(CustomerService::query()->whereKey(999999)->exists())->toBeFalse();
})->with(['unknown id', 'paid service', 'cancelled free service']);
