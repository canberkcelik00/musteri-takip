<?php

namespace App\Support\Calendar;

/**
 * Puts the timed entries of one day side by side. Entries that overlap in time share the column's width: each gets a
 * lane (0, 1, ...) and `lanes`, the number of lanes of its overlapping group. An entry without an end counts as half
 * an hour, and no entry is drawn shorter than that.
 */
final class DayLayout
{
    /** The shortest an entry is drawn, in minutes. */
    public const int MINIMUM_MINUTES = 30;

    /**
     * @param  list<CalendarEntry>  $entries  the day's timed entries
     * @return list<array{entry: CalendarEntry, from: int, to: int, lane: int, lanes: int}>
     */
    public static function place(array $entries): array
    {
        $items = [];

        foreach ($entries as $entry) {
            if ($entry->allDay || $entry->start === null) {
                continue;
            }

            $from = self::minutes($entry->start);
            $to = max($entry->end !== null ? self::minutes($entry->end) : 0, $from + self::MINIMUM_MINUTES);
            $items[] = ['entry' => $entry, 'from' => $from, 'to' => min($to, 24 * 60), 'lane' => 0, 'lanes' => 1];
        }

        usort($items, fn (array $a, array $b): int => [$a['from'], $b['to']] <=> [$b['from'], $a['to']]);

        $placed = [];
        $group = [];
        $laneEnds = [];
        $groupEnd = 0;

        foreach ($items as $item) {
            if ($group !== [] && $item['from'] >= $groupEnd) {
                $placed = [...$placed, ...self::close($group, count($laneEnds))];
                $group = [];
                $laneEnds = [];
            }

            $lane = 0;

            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $item['from']) {
                $lane++;
            }

            $laneEnds[$lane] = $item['to'];
            $item['lane'] = $lane;
            $group[] = $item;
            $groupEnd = max($groupEnd, $item['to']);
        }

        return [...$placed, ...self::close($group, count($laneEnds))];
    }

    /**
     * @param  list<array{entry: CalendarEntry, from: int, to: int, lane: int, lanes: int}>  $group
     * @return list<array{entry: CalendarEntry, from: int, to: int, lane: int, lanes: int}>
     */
    private static function close(array $group, int $lanes): array
    {
        return array_map(fn (array $item): array => [...$item, 'lanes' => max($lanes, 1)], $group);
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
