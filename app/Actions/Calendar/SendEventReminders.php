<?php

namespace App\Actions\Calendar;

use App\Enums\EventRepeat;
use App\Mail\CalendarEventReminderMail;
use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use App\Support\Calendar\EventOccurrences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mail the attendees of every event day whose reminder time has come ("henüz yapılmadıysa yap", docs/PRD.md,
 * Takvim). Called on every scheduler run: a day is reminded once, on the first run at or after `start - remind_minutes`,
 * however many runs were missed. A day that already started is never reminded, so a long outage does not send
 * stale mail. One `calendar_event_reminders` row per (event, day) is written after the send; it is what makes a
 * second run do nothing, and each day of a weekly or monthly series has its own.
 *
 * A day whose every send failed writes no row and is tried again on the next run (until it starts). A day with at
 * least one successful send is recorded, so the attendees who were reached are not mailed twice; the failures are
 * logged. A day with no attendees is skipped without a row.
 */
class SendEventReminders
{
    /** The longest reminder the event window accepts is 30 days; one more day covers the day boundary. */
    private const HORIZON_DAYS = 31;

    public function __construct(private readonly EventOccurrences $occurrences) {}

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $events = CalendarEvent::query()
            ->with([
                'customer:id,short_name',
                'attendees',
                'reminders' => fn (Relation $reminders) => $reminders->whereDate('occurs_on', '>=', $today->toDateString()),
            ])
            ->whereNotNull('remind_minutes')
            ->whereDate('starts_on', '<=', $today->copy()->addDays(self::HORIZON_DAYS)->toDateString())
            ->where(fn (Builder $query): Builder => $query
                ->whereDate('starts_on', '>=', $today->toDateString())
                ->orWhere(fn (Builder $series): Builder => $series
                    ->where('repeat', '!=', EventRepeat::None)
                    ->where(fn (Builder $open): Builder => $open
                        ->whereNull('repeat_until')
                        ->orWhereDate('repeat_until', '>=', $today->toDateString()))))
            ->get();

        foreach ($events as $event) {
            $reminded = $event->reminders
                ->map(fn (CalendarEventReminder $reminder): string => $reminder->occurs_on->toDateString())
                ->all();
            $minutes = (int) $event->remind_minutes;

            foreach ($this->occurrences->between($event, $today, $now->copy()->addMinutes($minutes)->startOfDay()) as $day) {
                $start = Carbon::parse($day->toDateString().' '.($event->start_time ?? '00:00:00'), config('app.timezone'));

                if ($start->lte($now) || $now->lt($start->copy()->subMinutes($minutes))) {
                    continue;
                }

                if (in_array($day->toDateString(), $reminded, true)) {
                    continue;
                }

                $this->remind($event, $day, $result);
            }
        }

        return $result;
    }

    /**
     * @param  array{sent: int, skipped: int, failed: int}  $result
     */
    private function remind(CalendarEvent $event, Carbon $day, array &$result): void
    {
        if ($event->attendees->isEmpty()) {
            $result['skipped']++;

            return;
        }

        $reached = 0;

        foreach ($event->attendees as $attendee) {
            try {
                Mail::to($attendee->email)->send(new CalendarEventReminderMail($event, $attendee, $day));
                $reached++;
            } catch (Throwable $exception) {
                Log::error('Calendar event reminder could not be sent.', [
                    'calendar_event_id' => $event->id,
                    'occurs_on' => $day->toDateString(),
                    'user_id' => $attendee->id,
                    'exception' => $exception,
                ]);
            }
        }

        if ($reached === 0) {
            $result['failed']++;

            return;
        }

        CalendarEventReminder::query()->create([
            'calendar_event_id' => $event->id,
            'occurs_on' => $day->toDateString(),
            'sent_at' => now(),
        ]);

        $result['sent']++;
    }
}
