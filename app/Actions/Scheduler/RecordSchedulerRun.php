<?php

namespace App\Actions\Scheduler;

use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Record that app:process-customer-services finished, with the customer services that failed
 * in it (G-5.6). Each run replaces the previous record, so a fixed service no longer shows.
 */
class RecordSchedulerRun
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<int>  $failedServiceIds
     */
    public function handle(array $failedServiceIds = []): void
    {
        $ids = array_values(array_unique($failedServiceIds));
        sort($ids);

        $this->settings->markSchedulerRun(Carbon::now(config('app.timezone'))->startOfSecond(), $ids);
    }
}
