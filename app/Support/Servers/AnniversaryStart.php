<?php

namespace App\Support\Servers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Moves an account's opening date to the anniversary that covers today, so a pulled service
 * starts in its current year and no past period is ever charged (PRD "Sunucu entegrasyonu").
 */
final class AnniversaryStart
{
    /**
     * The latest anniversary of `$date` that is not after `$today`; a date in the future is
     * returned as it is. 29 February falls on 28 February in non-leap years.
     */
    public static function covering(CarbonInterface $date, CarbonInterface $today): CarbonImmutable
    {
        $date = CarbonImmutable::parse($date->toDateString());
        $today = CarbonImmutable::parse($today->toDateString());

        if ($date->greaterThan($today)) {
            return $date;
        }

        $years = $today->year - $date->year;
        $candidate = $date->addYearsNoOverflow($years);

        return $candidate->greaterThan($today) ? $date->addYearsNoOverflow($years - 1) : $candidate;
    }
}
