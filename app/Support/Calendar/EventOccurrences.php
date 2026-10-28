<?php

namespace App\Support\Calendar;

use App\Enums\EventRepeat;
use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;

/**
 * Which days an event falls on. The repeats are never stored; the calendar and the reminders both expand them here.
 */
class EventOccurrences
{
    /**
     * The days of one event inside the range. A monthly event keeps the day of its first date and falls on the last
     * day of a shorter month (31 January, 28 February, 31 March). The n-th month is counted from the first date, so a
     * short month does not move the later ones.
     *
     * @return list<Carbon>
     */
    public function between(CalendarEvent $event, Carbon $from, Carbon $to): array
    {
        $first = $event->starts_on->copy()->startOfDay();
        $last = $event->repeat_until !== null && $event->repeat_until->lt($to) ? $event->repeat_until->copy()->startOfDay() : $to;

        if (! $event->repeats()) {
            return $first->between($from, $to) ? [$first] : [];
        }

        $days = [];
        $step = 0;

        if ($event->repeat === EventRepeat::Weekly) {
            $step = max(0, intdiv((int) $first->diffInDays($from, false), 7));

            for ($day = $first->copy()->addWeeks($step); $day->lte($last); $day = $first->copy()->addWeeks(++$step)) {
                if ($day->gte($from)) {
                    $days[] = $day;
                }
            }

            return $days;
        }

        $step = max(0, ($from->year - $first->year) * 12 + $from->month - $first->month - 1);

        for ($day = $first->copy()->addMonthsNoOverflow($step); $day->lte($last); $day = $first->copy()->addMonthsNoOverflow(++$step)) {
            if ($day->gte($from)) {
                $days[] = $day;
            }
        }

        return $days;
    }
}
