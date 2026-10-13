<?php

use App\Actions\Scheduler\CheckSchedulerHealth;
use App\Actions\Scheduler\RecordSchedulerRun;
use App\Enums\SchedulerStatus;
use App\Enums\SettingKey;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    fakePendingRelease(false);
    $this->travelTo('2026-10-13 14:00:00');
});

function schedulerHealth(): array
{
    return app(CheckSchedulerHealth::class)->handle();
}

it('reports a scheduler that never ran', function () {
    expect(schedulerHealth())
        ->status->toBe(SchedulerStatus::NeverRan)
        ->last_run_at->toBeNull()
        ->failed_services->toBeEmpty();
});

it('is healthy up to an hour after the last run and stale after it', function () {
    app(RecordSchedulerRun::class)->handle();

    $this->travelTo('2026-10-13 15:00:00');
    expect(schedulerHealth()['status'])->toBe(SchedulerStatus::Healthy);

    $this->travelTo('2026-10-13 15:00:01');
    expect(schedulerHealth())
        ->status->toBe(SchedulerStatus::Stale)
        ->and(schedulerHealth()['last_run_at']?->format('d.m.Y H:i'))->toBe('13.10.2026 14:00');
});

it('names a pending release as the reason of a stale scheduler', function () {
    app(RecordSchedulerRun::class)->handle();
    fakePendingRelease(true);

    expect(schedulerHealth()['status'])->toBe(SchedulerStatus::Healthy);

    $this->travelTo('2026-10-13 15:01:00');
    expect(schedulerHealth()['status'])->toBe(SchedulerStatus::PendingRelease);
});

it('returns the services that failed in the last run, leaving deleted ones out', function () {
    $first = CustomerService::factory()->create();
    $second = CustomerService::factory()->create();
    $deleted = CustomerService::factory()->create();

    app(RecordSchedulerRun::class)->handle([$second->id, $deleted->id, $first->id, $second->id]);
    $deleted->delete();

    expect(app(Settings::class)->schedulerFailedServiceIds())->toBe([$first->id, $second->id, $deleted->id])
        ->and(schedulerHealth()['failed_services']->modelKeys())->toBe([$first->id, $second->id])
        ->and(schedulerHealth()['status'])->toBe(SchedulerStatus::Healthy);
});

it('forgets the failed services when the next run has none', function () {
    $customerService = CustomerService::factory()->create();

    app(RecordSchedulerRun::class)->handle([$customerService->id]);
    app(RecordSchedulerRun::class)->handle();

    expect(schedulerHealth()['failed_services'])->toBeEmpty()
        ->and(app(Settings::class)->get(SettingKey::SchedulerFailedServiceIds))->toBeNull();
});

it('treats an unreadable last run as no run and logs it', function () {
    Log::spy();
    app(Settings::class)->set(SettingKey::SchedulerLastRunAt, 'not a date');

    expect(schedulerHealth()['status'])->toBe(SchedulerStatus::NeverRan);
    Log::shouldHaveReceived('warning')->once();
});

it('treats unreadable failed service ids as no failures and logs them', function (string $value) {
    Log::spy();
    app(RecordSchedulerRun::class)->handle();
    app(Settings::class)->set(SettingKey::SchedulerFailedServiceIds, $value);

    expect(schedulerHealth())
        ->status->toBe(SchedulerStatus::Healthy)
        ->failed_services->toBeEmpty();
    Log::shouldHaveReceived('warning')->once();
})->with([
    'not json' => ['not json'],
    'not a list of ids' => ['["1", 0]'],
]);
