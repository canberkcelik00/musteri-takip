<?php

namespace App\Actions\Scheduler;

use App\Actions\Deploy\DeployRelease;
use App\Enums\SchedulerStatus;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The scheduler's health for the warning on "Bugün" (G-5.6): whether
 * app:process-customer-services ran within the last hour, and which customer services
 * failed in its last run (the per-service error isolation of G-3.9, T-50).
 */
class CheckSchedulerHealth
{
    /**
     * The hosting cron runs every 15 minutes, so an hour is four missed runs.
     */
    public const STALE_AFTER_MINUTES = 60;

    public function __construct(
        private readonly Settings $settings,
        private readonly DeployRelease $deployRelease,
    ) {}

    /**
     * Failed services that were deleted since the run are left out.
     *
     * @return array{status: SchedulerStatus, last_run_at: ?Carbon, failed_services: Collection<int, CustomerService>}
     */
    public function handle(): array
    {
        $lastRunAt = $this->settings->schedulerLastRunAt();
        $failedServiceIds = $this->settings->schedulerFailedServiceIds();

        return [
            'status' => $this->status($lastRunAt),
            'last_run_at' => $lastRunAt,
            'failed_services' => $failedServiceIds === []
                ? new Collection
                : CustomerService::query()->with('customer')->whereKey($failedServiceIds)->orderBy('id')->get(),
        ];
    }

    private function status(?Carbon $lastRunAt): SchedulerStatus
    {
        if ($lastRunAt === null) {
            return SchedulerStatus::NeverRan;
        }

        if ($lastRunAt->greaterThanOrEqualTo(Carbon::now(config('app.timezone'))->subMinutes(self::STALE_AFTER_MINUTES))) {
            return SchedulerStatus::Healthy;
        }

        // The scheduled jobs are skipped while a pulled release is not deployed (routes/console.php).
        return $this->deployRelease->hasPendingRelease() ? SchedulerStatus::PendingRelease : SchedulerStatus::Stale;
    }
}
