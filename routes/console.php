<?php

use App\Actions\Deploy\DeployRelease;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The hosting cron calls `schedule:run` every 15 minutes; every call runs these, so a
 * cron call that drifts by a minute is not skipped. The actions only do what is not done yet.
 *
 * The deploy is the backup of cPanel's deploy task (.cpanel.yml) and runs first. It has its own file
 * lock, so it needs no `withoutOverlapping` (which would use the database cache that
 * does not exist before the first deploy).
 */
Schedule::command('app:deploy')
    ->everyMinute();

// Skipped while an uploaded release is not deployed yet: the schema may still be old.
Schedule::command('app:process-customer-services')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());
