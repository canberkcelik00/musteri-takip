<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The hosting cron calls `schedule:run` every 15 minutes; every call runs this, so a
 * cron call that drifts by a minute is not skipped. The actions only do what is not done yet.
 */
Schedule::command('app:process-customer-services')
    ->everyMinute()
    ->withoutOverlapping(60);
