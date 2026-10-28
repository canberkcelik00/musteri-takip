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
 */

// Skipped while a pulled release is not deployed yet (the deploy address was not opened): the schema may still be old.
Schedule::command('app:process-customer-services')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// After the customer services, so a renewal charge created in the same run is reminded in that run.
Schedule::command('app:send-renewal-reminders')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// After the customer services, so the renewal charges created in the same run are in the digest.
Schedule::command('app:send-digest')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// E-mails the attendees of the calendar events whose reminder time has come; an event that already started is not reminded.
Schedule::command('app:send-event-reminders')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// Looks up which domains point to Cloudflare, within a time budget; what does not fit waits for the next run.
Schedule::command('app:check-domain-dns')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// Reads the SSL certificate expiry of the domains not read for 24 hours, within a time budget (G-16.3).
Schedule::command('app:check-ssl')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// Reads the registration end and the registrar of the domains not read for 24 hours, within a time budget (G-16.4).
Schedule::command('app:check-registration')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// Checks whether the domains of hosting items still point to their server, within a time budget (G-16.5).
Schedule::command('app:check-hosting-points')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());

// Reads the usage of the linked hosting accounts, within a time budget; what does not fit waits for the next run.
Schedule::command('app:refresh-server-usage')
    ->everyMinute()
    ->withoutOverlapping(60)
    ->when(fn (): bool => ! app(DeployRelease::class)->hasPendingRelease());
