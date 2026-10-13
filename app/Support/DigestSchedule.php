<?php

namespace App\Support;

use App\Enums\DigestFrequency;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The scheduled moments of the digest e-mail, from the settings (frequency, day of month,
 * time), in the application time zone (Europe/Istanbul). The settings are read on every call,
 * so a changed setting applies to the next run.
 *
 * Daily: every day at the digest time. Monthly: on the configured day (1–28) at the digest time.
 */
class DigestSchedule
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * The latest scheduled moment at or before $now.
     */
    public function lastScheduledAt(CarbonInterface $now): Carbon
    {
        $moment = $this->momentInPeriodOf($now);

        if ($moment->greaterThan($now)) {
            $moment = $this->momentInPeriodOf($this->previousPeriod($moment));
        }

        return $moment;
    }

    /**
     * The first scheduled moment after $now.
     */
    public function nextScheduledAt(CarbonInterface $now): Carbon
    {
        $moment = $this->momentInPeriodOf($now);

        if ($moment->lessThanOrEqualTo($now)) {
            $moment = $this->momentInPeriodOf($this->nextPeriod($moment));
        }

        return $moment;
    }

    /**
     * The scheduled moment in the day (daily) or month (monthly) that contains $date.
     */
    private function momentInPeriodOf(CarbonInterface $date): Carbon
    {
        $moment = Carbon::instance($date)->setTimezone(config('app.timezone'));

        if ($this->settings->digestFrequency() === DigestFrequency::Monthly) {
            $moment->setDay($this->settings->digestDayOfMonth());
        }

        return $moment->setTimeFromTimeString($this->settings->digestTime());
    }

    private function previousPeriod(Carbon $moment): Carbon
    {
        return $this->settings->digestFrequency() === DigestFrequency::Monthly
            ? $moment->copy()->startOfMonth()->subMonthNoOverflow()
            : $moment->copy()->subDay();
    }

    private function nextPeriod(Carbon $moment): Carbon
    {
        return $this->settings->digestFrequency() === DigestFrequency::Monthly
            ? $moment->copy()->startOfMonth()->addMonthNoOverflow()
            : $moment->copy()->addDay();
    }
}
