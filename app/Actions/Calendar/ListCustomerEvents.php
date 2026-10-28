<?php

namespace App\Actions\Calendar;

use App\Enums\EventRepeat;
use App\Models\CalendarEvent;
use App\Models\Customer;
use App\Support\Calendar\EventOccurrences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The customer card's "Yaklaşan etkinlikler": the next days the customer's events fall on, today included, never a day
 * that has passed. One query for the customer's events; a repeating event shows once, on its next day.
 */
class ListCustomerEvents
{
    public const LIMIT = 5;

    /**
     * @return list<array{event: CalendarEvent, date: Carbon}> soonest first, all-day events before timed ones on a day
     */
    public function handle(Customer $customer, int $limit = self::LIMIT): array
    {
        $today = today();

        $events = $customer->calendarEvents()
            ->where(function (Builder $query) use ($today): void {
                $query->where(fn (Builder $single): Builder => $single
                    ->where('repeat', EventRepeat::None)
                    ->whereDate('starts_on', '>=', $today->toDateString()))
                    ->orWhere(fn (Builder $series): Builder => $series
                        ->where('repeat', '!=', EventRepeat::None)
                        ->where(fn (Builder $open): Builder => $open
                            ->whereNull('repeat_until')
                            ->orWhereDate('repeat_until', '>=', $today->toDateString())));
            })
            ->get();

        $occurrences = new EventOccurrences;
        $upcoming = [];

        foreach ($events as $event) {
            // Two months always hold the next day of a weekly or monthly series; a single event has no horizon.
            $next = $event->repeats()
                ? ($occurrences->between($event, $today, $today->copy()->addMonths(2))[0] ?? null)
                : $event->starts_on->copy()->startOfDay();

            if ($next !== null) {
                $upcoming[] = ['event' => $event, 'date' => $next];
            }
        }

        usort($upcoming, fn (array $a, array $b): int => [$a['date']->timestamp, $a['event']->all_day ? 0 : 1, $a['event']->start_time ?? '', $a['event']->id]
            <=> [$b['date']->timestamp, $b['event']->all_day ? 0 : 1, $b['event']->start_time ?? '', $b['event']->id]);

        return array_slice($upcoming, 0, $limit);
    }
}
