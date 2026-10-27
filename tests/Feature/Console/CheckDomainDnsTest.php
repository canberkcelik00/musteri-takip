<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('runs on every scheduler call without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'app:check-domain-dns'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60);
});

it('finishes successfully and reports what it did', function () {
    $this->artisan('app:check-domain-dns')
        ->expectsOutputToContain(__('domains.check_result', ['checked' => 0, 'failed' => 0, 'remaining' => 0]))
        ->assertSuccessful();
});
