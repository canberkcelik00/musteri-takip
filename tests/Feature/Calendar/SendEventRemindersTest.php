<?php

use App\Actions\Calendar\SendEventReminders;
use App\Enums\EventRepeat;
use App\Mail\CalendarEventReminderMail;
use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

function reminderMoment(string $moment): Carbon
{
    return Carbon::parse($moment, 'Europe/Istanbul');
}

/**
 * An event on the given day and time, reminded `$remind` minutes before, with one attendee.
 *
 * @param  array<string, mixed>  $attributes
 */
function remindedEvent(string $date, string $time, int $remind, array $attributes = []): CalendarEvent
{
    $event = CalendarEvent::factory()->create(array_merge([
        'starts_on' => $date,
        'start_time' => $time,
        'end_time' => null,
        'remind_minutes' => $remind,
    ], $attributes));
    $event->attendees()->attach(User::factory()->developer()->create());

    return $event;
}

beforeEach(function () {
    Mail::fake();
});

it('sends the reminder once the reminder time has come, to every attendee', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:30'));
    $event = remindedEvent('2026-11-03', '10:00', 30);
    $event->attendees()->attach(User::factory()->developer()->create());

    $result = app(SendEventReminders::class)->handle();

    expect($result)->toBe(['sent' => 1, 'skipped' => 0, 'failed' => 0]);
    Mail::assertSent(CalendarEventReminderMail::class, 2);
    expect(CalendarEventReminder::query()->count())->toBe(1);
});

it('waits until the reminder time', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:29'));
    remindedEvent('2026-11-03', '10:00', 30);

    app(SendEventReminders::class)->handle();

    Mail::assertNothingSent();
});

it('does not send a second reminder when it runs again', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:30'));
    remindedEvent('2026-11-03', '10:00', 30);

    app(SendEventReminders::class)->handle();
    $this->travelTo(reminderMoment('2026-11-03 09:31'));
    $second = app(SendEventReminders::class)->handle();

    expect($second['sent'])->toBe(0);
    Mail::assertSent(CalendarEventReminderMail::class, 1);
    expect(CalendarEventReminder::query()->count())->toBe(1);
});

it('still sends when the cron call came 15 minutes late', function () {
    remindedEvent('2026-11-03', '10:00', 30);

    $this->travelTo(reminderMoment('2026-11-03 09:45'));
    $result = app(SendEventReminders::class)->handle();

    expect($result['sent'])->toBe(1);
    Mail::assertSent(CalendarEventReminderMail::class, 1);
});

it('never reminds an event that already started', function () {
    $this->travelTo(reminderMoment('2026-11-03 10:00'));
    remindedEvent('2026-11-03', '10:00', 30);
    remindedEvent('2026-11-03', '08:00', 15);

    $result = app(SendEventReminders::class)->handle();

    expect($result['sent'])->toBe(0);
    Mail::assertNothingSent();
    expect(CalendarEventReminder::query()->count())->toBe(0);
});

it('does nothing for an event without a reminder', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:55'));
    $event = remindedEvent('2026-11-03', '10:00', 30);
    $event->update(['remind_minutes' => null]);

    app(SendEventReminders::class)->handle();

    Mail::assertNothingSent();
});

it('reminds a reminder of a day before the day before', function () {
    $this->travelTo(reminderMoment('2026-11-02 10:00'));
    remindedEvent('2026-11-03', '10:00', 1440);

    $result = app(SendEventReminders::class)->handle();

    expect($result['sent'])->toBe(1);
});

it('reminds an all-day event from the start of the day', function () {
    $this->travelTo(reminderMoment('2026-11-02 23:30'));
    remindedEvent('2026-11-03', '10:00', 60, ['all_day' => true, 'start_time' => null]);

    $result = app(SendEventReminders::class)->handle();

    expect($result['sent'])->toBe(1);
});

it('reminds every week of a weekly series on its own', function () {
    $event = remindedEvent('2026-11-03', '10:00', 30, ['repeat' => EventRepeat::Weekly]);

    $this->travelTo(reminderMoment('2026-11-03 09:40'));
    app(SendEventReminders::class)->handle();
    $this->travelTo(reminderMoment('2026-11-03 09:50'));
    app(SendEventReminders::class)->handle();
    $this->travelTo(reminderMoment('2026-11-10 09:40'));
    $third = app(SendEventReminders::class)->handle();

    expect($third['sent'])->toBe(1);
    Mail::assertSent(CalendarEventReminderMail::class, 2);
    expect($event->reminders()->orderBy('occurs_on')->get()->map(fn ($reminder) => $reminder->occurs_on->toDateString())->all())
        ->toBe(['2026-11-03', '2026-11-10']);
});

it('does not remind a series after its end date', function () {
    $this->travelTo(reminderMoment('2026-11-10 09:40'));
    remindedEvent('2026-11-03', '10:00', 30, ['repeat' => EventRepeat::Weekly, 'repeat_until' => '2026-11-09']);

    app(SendEventReminders::class)->handle();

    Mail::assertNothingSent();
});

it('skips an event nobody attends without recording it', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:40'));
    CalendarEvent::factory()->create(['starts_on' => '2026-11-03', 'start_time' => '10:00:00', 'end_time' => null, 'remind_minutes' => 30]);

    $result = app(SendEventReminders::class)->handle();

    expect($result)->toBe(['sent' => 0, 'skipped' => 1, 'failed' => 0]);
    expect(CalendarEventReminder::query()->count())->toBe(0);
});

it('records nothing when every send failed, so the next run tries again', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:40'));
    remindedEvent('2026-11-03', '10:00', 30);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    $failed = app(SendEventReminders::class)->handle();

    expect($failed)->toBe(['sent' => 0, 'skipped' => 0, 'failed' => 1]);
    expect(CalendarEventReminder::query()->count())->toBe(0);
});

it('runs from the command and reports what it did', function () {
    $this->travelTo(reminderMoment('2026-11-03 09:40'));
    remindedEvent('2026-11-03', '10:00', 30);

    $this->artisan('app:send-event-reminders')
        ->expectsOutputToContain(__('calendar.reminder_command.summary', ['sent' => 1, 'skipped' => 0, 'failed' => 0]))
        ->assertSuccessful();
});

it('is scheduled on every call without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'app:send-event-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60);
});

it('words the mail with the day and the time', function () {
    $event = remindedEvent('2026-11-03', '10:00', 30, ['title' => 'Sunum', 'end_time' => '11:00', 'location' => 'Ofis']);
    $mail = new CalendarEventReminderMail($event, $event->attendees->first(), reminderMoment('2026-11-03'));

    $mail->assertHasSubject('Hatırlatma: Sunum · 03.11.2026 · 10:00–11:00');
    $mail->assertSeeInText('Ofis');
});
