<?php

namespace App\Actions\Calendar;

use App\Enums\EventRepeat;
use App\Exceptions\InvalidCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\Customer;
use Illuminate\Support\Carbon;

class NormalizeCalendarEventAttributes
{
    /**
     * The values to store for an event, with the rules that make them consistent: an all-day event has no times, a timed
     * event needs a start and its end comes after it, a series that does not repeat has no end date and one that repeats
     * does not end before it starts. Only moving the event to an archived customer is refused (`$existing` may stay with
     * one archived since).
     *
     * @param  array{title: string, starts_on: mixed, start_time?: ?string, end_time?: ?string, all_day?: bool, customer_id?: ?int, location?: ?string, notes?: ?string, remind_minutes?: ?int, repeat?: EventRepeat|string|null, repeat_until?: mixed}  $attributes
     * @return array<string, mixed>
     *
     * @throws InvalidCalendarEvent
     */
    public function handle(array $attributes, ?CalendarEvent $existing = null): array
    {
        $startsOn = Carbon::parse($attributes['starts_on'])->startOfDay();
        $allDay = (bool) ($attributes['all_day'] ?? false);
        $startTime = null;
        $endTime = null;

        if (! $allDay) {
            $startTime = filled($attributes['start_time'] ?? null) ? $this->time($attributes['start_time']) : null;
            $endTime = filled($attributes['end_time'] ?? null) ? $this->time($attributes['end_time']) : null;

            if ($startTime === null) {
                throw InvalidCalendarEvent::startTimeRequired();
            }

            if ($endTime !== null && $endTime <= $startTime) {
                throw InvalidCalendarEvent::endNotAfterStart();
            }
        }

        $repeat = $attributes['repeat'] ?? EventRepeat::None;
        $repeat = $repeat instanceof EventRepeat ? $repeat : EventRepeat::from($repeat);
        $repeatUntil = $repeat === EventRepeat::None || blank($attributes['repeat_until'] ?? null)
            ? null
            : Carbon::parse($attributes['repeat_until'])->startOfDay();

        if ($repeatUntil !== null && $repeatUntil->lt($startsOn)) {
            throw InvalidCalendarEvent::repeatUntilBeforeStart();
        }

        $customerId = $attributes['customer_id'] ?? null;

        if ($customerId !== null && $customerId !== $existing?->customer_id && Customer::query()->findOrFail($customerId)->isArchived()) {
            throw InvalidCalendarEvent::customerArchived();
        }

        return [
            'title' => $attributes['title'],
            'starts_on' => $startsOn,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'all_day' => $allDay,
            'customer_id' => $customerId,
            'location' => filled($attributes['location'] ?? null) ? $attributes['location'] : null,
            'notes' => filled($attributes['notes'] ?? null) ? $attributes['notes'] : null,
            'remind_minutes' => filled($attributes['remind_minutes'] ?? null) ? (int) $attributes['remind_minutes'] : null,
            'repeat' => $repeat,
            'repeat_until' => $repeatUntil,
        ];
    }

    private function time(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }
}
