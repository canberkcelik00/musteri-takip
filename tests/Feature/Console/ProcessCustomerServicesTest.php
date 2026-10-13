<?php

use App\Enums\Currency;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

/**
 * An active 4.000 TL service for the period 01.03.2026 – 01.03.2027.
 *
 * @param  array<string, mixed>  $attributes
 */
function scheduledService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

/**
 * @return array{0: string, 1: string, 2: list<string>}
 */
function scheduledServiceState(CustomerService $customerService): array
{
    $customerService->refresh();

    return [
        $customerService->start_date->toDateString(),
        $customerService->end_date->toDateString(),
        $customerService->charges()->pluck('period_start')->map->toDateString()->sort()->values()->all(),
    ];
}

it('runs on every scheduler call without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, 'app:process-customer-services'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60);
});

it('creates no duplicate records when run repeatedly', function () {
    $customerService = scheduledService();

    $this->travelTo('2027-01-30 00:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();
    $afterFirstRun = scheduledServiceState($customerService);

    $this->artisan('app:process-customer-services')->assertSuccessful();
    $this->travelTo('2027-01-30 00:15:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect($afterFirstRun)->toBe(['2026-03-01', '2027-03-01', ['2027-03-01']])
        ->and(scheduledServiceState($customerService))->toBe($afterFirstRun)
        ->and(Charge::query()->count())->toBe(1);
});

it('completes the missed work on the first run after days of missed runs', function () {
    $customerService = scheduledService();

    $this->travelTo('2027-03-10 09:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(scheduledServiceState($customerService))->toBe(['2027-03-01', '2028-03-01', ['2027-03-01']]);

    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(scheduledServiceState($customerService))->toBe(['2027-03-01', '2028-03-01', ['2027-03-01']])
        ->and(Charge::query()->count())->toBe(1);
});

it('catches up several periods and the renewal window reached in the same run', function () {
    $customerService = scheduledService();

    $this->travelTo('2029-02-15 09:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(scheduledServiceState($customerService))
        ->toBe(['2028-03-01', '2029-03-01', ['2027-03-01', '2028-03-01', '2029-03-01']]);

    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(Charge::query()->count())->toBe(3);
});

it('advances free and skips cancelled services without charging them', function () {
    $free = CustomerService::factory()->free()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);
    $cancelled = CustomerService::factory()->cancelled()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    $this->travelTo('2027-03-10 09:00:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(scheduledServiceState($free))->toBe(['2027-03-01', '2028-03-01', []])
        ->and(scheduledServiceState($cancelled))->toBe(['2026-03-01', '2027-03-01', []]);
});

it('keeps processing the other services when one fails and reports the failure', function () {
    $broken = scheduledService(['price' => 0]);
    $advancing = scheduledService();
    $renewing = scheduledService(['start_date' => '2026-04-01', 'end_date' => '2027-04-01']);
    Log::spy();

    $this->travelTo('2027-03-10 09:00:00');
    $this->artisan('app:process-customer-services')
        ->expectsOutputToContain('Hata veren hizmet: 2')
        ->assertFailed();

    expect(scheduledServiceState($advancing))->toBe(['2027-03-01', '2028-03-01', ['2027-03-01']])
        ->and(scheduledServiceState($renewing))->toBe(['2026-04-01', '2027-04-01', ['2027-04-01']])
        ->and(scheduledServiceState($broken))->toBe(['2026-03-01', '2027-03-01', []]);

    Log::shouldHaveReceived('error')
        ->twice()
        ->withArgs(fn (string $message, array $context) => $context['customer_service_id'] === $broken->id);
});

it('records the finished run for the scheduler health check', function () {
    $this->travelTo('2027-01-30 10:15:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(app(Settings::class)->schedulerLastRunAt()?->toDateTimeString())->toBe('2027-01-30 10:15:00')
        ->and(app(Settings::class)->schedulerFailedServiceIds())->toBe([]);
});

it('records the failing services of the run once and forgets them after a clean run', function () {
    $broken = scheduledService(['price' => 0]);
    scheduledService();
    Log::spy();

    $this->travelTo('2027-03-10 09:00:00');
    $this->artisan('app:process-customer-services')->assertFailed();

    expect(app(Settings::class)->schedulerLastRunAt()?->toDateTimeString())->toBe('2027-03-10 09:00:00')
        ->and(app(Settings::class)->schedulerFailedServiceIds())->toBe([$broken->id]);

    $broken->update(['price' => 400000]);
    $this->travelTo('2027-03-10 09:15:00');
    $this->artisan('app:process-customer-services')->assertSuccessful();

    expect(app(Settings::class)->schedulerLastRunAt()?->toDateTimeString())->toBe('2027-03-10 09:15:00')
        ->and(app(Settings::class)->schedulerFailedServiceIds())->toBe([]);
});
