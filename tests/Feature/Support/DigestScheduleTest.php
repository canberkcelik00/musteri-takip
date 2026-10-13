<?php

use App\Enums\DigestFrequency;
use App\Enums\SettingKey;
use App\Support\DigestSchedule;
use App\Support\Settings;
use Illuminate\Support\Carbon;

function digestScheduleSettings(DigestFrequency $frequency, int $day = 15, string $time = '09:00'): DigestSchedule
{
    $settings = app(Settings::class);
    $settings->set(SettingKey::DigestFrequency, $frequency->value);
    $settings->set(SettingKey::DigestDayOfMonth, (string) $day);
    $settings->set(SettingKey::DigestTime, $time);

    return app(DigestSchedule::class);
}

function istanbul(string $moment): Carbon
{
    return Carbon::parse($moment, 'Europe/Istanbul');
}

it('finds the last and next daily moments', function (string $now, string $last, string $next) {
    $schedule = digestScheduleSettings(DigestFrequency::Daily);

    expect($schedule->lastScheduledAt(istanbul($now))->toDateTimeString())->toBe($last)
        ->and($schedule->nextScheduledAt(istanbul($now))->toDateTimeString())->toBe($next);
})->with([
    'before the time' => ['2026-10-13 08:59:59', '2026-10-12 09:00:00', '2026-10-13 09:00:00'],
    'at the time' => ['2026-10-13 09:00:00', '2026-10-13 09:00:00', '2026-10-14 09:00:00'],
    'after the time' => ['2026-10-13 13:00:00', '2026-10-13 09:00:00', '2026-10-14 09:00:00'],
    'month end' => ['2026-11-01 08:00:00', '2026-10-31 09:00:00', '2026-11-01 09:00:00'],
    'year end' => ['2027-01-01 08:00:00', '2026-12-31 09:00:00', '2027-01-01 09:00:00'],
]);

it('finds the last and next monthly moments', function (string $now, string $last, string $next) {
    $schedule = digestScheduleSettings(DigestFrequency::Monthly);

    expect($schedule->lastScheduledAt(istanbul($now))->toDateTimeString())->toBe($last)
        ->and($schedule->nextScheduledAt(istanbul($now))->toDateTimeString())->toBe($next);
})->with([
    'before the day' => ['2026-10-14 12:00:00', '2026-09-15 09:00:00', '2026-10-15 09:00:00'],
    'on the day before the time' => ['2026-10-15 08:59:00', '2026-09-15 09:00:00', '2026-10-15 09:00:00'],
    'at the time' => ['2026-10-15 09:00:00', '2026-10-15 09:00:00', '2026-11-15 09:00:00'],
    'after the day' => ['2026-10-17 10:00:00', '2026-10-15 09:00:00', '2026-11-15 09:00:00'],
    'month end' => ['2026-10-31 23:59:00', '2026-10-15 09:00:00', '2026-11-15 09:00:00'],
    'year change back' => ['2027-01-10 09:00:00', '2026-12-15 09:00:00', '2027-01-15 09:00:00'],
    'year change forward' => ['2026-12-20 09:00:00', '2026-12-15 09:00:00', '2027-01-15 09:00:00'],
]);

it('handles the 28th through February and the 31st of a month', function () {
    $schedule = digestScheduleSettings(DigestFrequency::Monthly, 28);

    expect($schedule->lastScheduledAt(istanbul('2027-03-01 00:00:00'))->toDateTimeString())->toBe('2027-02-28 09:00:00')
        ->and($schedule->nextScheduledAt(istanbul('2027-01-31 12:00:00'))->toDateTimeString())->toBe('2027-02-28 09:00:00')
        ->and($schedule->lastScheduledAt(istanbul('2027-03-27 12:00:00'))->toDateTimeString())->toBe('2027-02-28 09:00:00');
});

it('returns the moments in Istanbul time for a moment given in another time zone', function () {
    $schedule = digestScheduleSettings(DigestFrequency::Daily);

    // 06:30 UTC is 09:30 in Istanbul: today's 09:00 has passed.
    $last = $schedule->lastScheduledAt(Carbon::parse('2026-10-13 06:30:00', 'UTC'));

    expect($last->toDateTimeString())->toBe('2026-10-13 09:00:00')
        ->and($last->timezoneName)->toBe('Europe/Istanbul');
});

it('follows a changed time, day and frequency at once', function () {
    $schedule = digestScheduleSettings(DigestFrequency::Monthly);
    $now = istanbul('2026-10-20 12:00:00');

    expect($schedule->lastScheduledAt($now)->toDateTimeString())->toBe('2026-10-15 09:00:00');

    app(Settings::class)->set(SettingKey::DigestDayOfMonth, '20');
    expect($schedule->lastScheduledAt($now)->toDateTimeString())->toBe('2026-10-20 09:00:00');

    app(Settings::class)->set(SettingKey::DigestTime, '14:30');
    expect($schedule->lastScheduledAt($now)->toDateTimeString())->toBe('2026-09-20 14:30:00')
        ->and($schedule->nextScheduledAt($now)->toDateTimeString())->toBe('2026-10-20 14:30:00');

    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Daily->value);
    expect($schedule->lastScheduledAt($now)->toDateTimeString())->toBe('2026-10-19 14:30:00');
});
