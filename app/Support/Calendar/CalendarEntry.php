<?php

namespace App\Support\Calendar;

use App\Enums\CalendarCategory;
use Carbon\CarbonInterface;

/**
 * One line of the calendar. It is built when the calendar is read and never stored. `start` and `end` are `H:i` and
 * empty on an all-day entry. `recorded` marks something that already happened (a payment, a sent quote, a finished
 * task); the other entries are dates ahead. A click opens `window` (with `params`) or goes to `url`; only an event
 * (`eventId`) is editable.
 *
 * @phpstan-type WindowParams array<string, int|string>
 */
final readonly class CalendarEntry
{
    /**
     * @param  array<string, int|string>  $params
     */
    public function __construct(
        public string $key,
        public CalendarCategory $category,
        public string $title,
        public CarbonInterface $date,
        public bool $allDay,
        public ?string $start = null,
        public ?string $end = null,
        public ?string $detail = null,
        public bool $recorded = false,
        public ?string $window = null,
        public array $params = [],
        public ?string $url = null,
        public ?int $eventId = null,
        public ?string $tone = null,
    ) {}

    /**
     * The colour of the entry's dot and strip: its own `tone` (a site that is up again is green though it belongs to
     * the alert category), else the category's.
     */
    public function color(): string
    {
        return $this->tone ?? $this->category->color();
    }

    public function isEditable(): bool
    {
        return $this->eventId !== null;
    }
}
