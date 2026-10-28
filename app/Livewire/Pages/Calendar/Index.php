<?php

namespace App\Livewire\Pages\Calendar;

use App\Actions\Calendar\ListCalendarEntries;
use App\Enums\CalendarCategory;
use App\Enums\EventRepeat;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CalendarEvent;
use App\Support\Calendar\CalendarEntry;
use App\Support\Calendar\DayLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The calendar (docs/UI.md §5 "Takvim"): day, week, month and list views of one range of dates. Everything the user may
 * see comes from ListCalendarEntries; this page only picks the range and the filters and draws the result. The view,
 * the date and the filters live in the address, so a calendar can be bookmarked and the back button works.
 *
 * @phpstan-type Column array{date: Carbon, short: string, number: int, today: bool, weekend: bool, allDay: list<CalendarEntry>, placed: list<array{entry: CalendarEntry, from: int, to: int, lane: int, lanes: int}>, now: ?int}
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesPermission;

    public const string VIEW_DAY = 'day';

    public const string VIEW_WEEK = 'week';

    public const string VIEW_MONTH = 'month';

    public const string VIEW_LIST = 'list';

    /** How many entries a month cell lists before "+N daha". */
    public const int MONTH_CELL_LIMIT = 3;

    /**
     * The filter chips: chip key => the categories it switches (the two alert categories share one chip) and the
     * permission a user needs to have anything to filter (empty: everyone).
     *
     * @var array<string, array{categories: list<CalendarCategory>, permission: ?Permission}>
     */
    private const array CHIPS = [
        'event' => ['categories' => [CalendarCategory::Event], 'permission' => null],
        'task' => ['categories' => [CalendarCategory::Task], 'permission' => Permission::TasksManage],
        'service_end' => ['categories' => [CalendarCategory::ServiceEnd], 'permission' => Permission::ServicesView],
        'charge_due' => ['categories' => [CalendarCategory::ChargeDue], 'permission' => Permission::MoneyView],
        'payment' => ['categories' => [CalendarCategory::Payment], 'permission' => Permission::MoneyView],
        'quote' => ['categories' => [CalendarCategory::Quote], 'permission' => Permission::MoneyView],
        'alert' => ['categories' => [CalendarCategory::AlertDown, CalendarCategory::AlertSoon], 'permission' => Permission::ServicesView],
    ];

    #[Url(except: 'week')]
    public string $view = self::VIEW_WEEK;

    /** The day the view is built around (`Y-m-d`); empty is today. */
    #[Url(except: '')]
    public string $date = '';

    /**
     * The chips switched off.
     *
     * @var list<string>
     */
    #[Url(except: [])]
    public array $hidden = [];

    #[Url(except: false)]
    public bool $mine = false;

    /** The event shown in the day view's detail panel (a CalendarEntry key). */
    public string $selected = '';

    /** The month of the day view's small calendar (`Y-m-01`); empty follows the date. */
    public string $miniMonth = '';

    public function updatedView(): void
    {
        $this->selected = '';
        $this->miniMonth = '';
    }

    public function previous(): void
    {
        $this->move(-1);
    }

    public function next(): void
    {
        $this->move(1);
    }

    public function goToday(): void
    {
        $this->setDate(Carbon::today());
    }

    public function openDay(string $date): void
    {
        $day = $this->parseDate($date);

        if ($day === null) {
            return;
        }

        $this->view = self::VIEW_DAY;
        $this->setDate($day);
    }

    /**
     * A day was picked (the small calendar, the week strip of the list).
     */
    public function pickDay(string $date): void
    {
        $day = $this->parseDate($date);

        if ($day !== null) {
            $this->setDate($day);
        }
    }

    /**
     * An event was clicked: its day opens with the event's detail beside it.
     */
    public function select(string $key, string $date): void
    {
        $day = $this->parseDate($date);

        if ($day === null || ! str_starts_with($key, 'event:')) {
            return;
        }

        $this->view = self::VIEW_DAY;
        $this->setDate($day);
        $this->selected = $key;
    }

    public function closeDetail(): void
    {
        $this->selected = '';
    }

    public function toggleChip(string $chip): void
    {
        if (! array_key_exists($chip, self::CHIPS)) {
            return;
        }

        $this->hidden = in_array($chip, $this->hidden, true)
            ? array_values(array_diff($this->hidden, [$chip]))
            : [...array_values(array_diff($this->hidden, [$chip])), $chip];
    }

    public function toggleMine(): void
    {
        $this->mine = ! $this->mine;
    }

    public function stepMiniMonth(int $step): void
    {
        $this->miniMonth = $this->miniAnchor()->addMonthsNoOverflow($step >= 0 ? 1 : -1)->toDateString();
    }

    /**
     * An event was changed or deleted in a window: the calendar is read again.
     */
    #[On('calendar-changed')]
    public function refresh(): void
    {
        $this->selected = '';
    }

    private function move(int $direction): void
    {
        $anchor = $this->anchor();

        $this->setDate(match ($this->viewName()) {
            self::VIEW_DAY => $anchor->addDays($direction),
            self::VIEW_MONTH => $anchor->startOfMonth()->addMonthsNoOverflow($direction),
            default => $anchor->addDays(7 * $direction),
        });
    }

    private function setDate(Carbon $day): void
    {
        $this->date = $day->isSameDay(Carbon::today()) ? '' : $day->toDateString();
        $this->selected = '';
        $this->miniMonth = '';
    }

    private function parseDate(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $value);

        return $day instanceof Carbon && $day->toDateString() === $value ? $day : null;
    }

    private function anchor(): Carbon
    {
        return $this->parseDate($this->date) ?? Carbon::today();
    }

    private function miniAnchor(): Carbon
    {
        return ($this->parseDate($this->miniMonth) ?? $this->anchor())->startOfMonth();
    }

    private function viewName(): string
    {
        return in_array($this->view, [self::VIEW_DAY, self::VIEW_WEEK, self::VIEW_MONTH, self::VIEW_LIST], true)
            ? $this->view
            : self::VIEW_WEEK;
    }

    /**
     * The first and last day the view draws: the day, the week (Monday to Sunday) or the month's weeks.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(): array
    {
        $anchor = $this->anchor();

        return match ($this->viewName()) {
            self::VIEW_DAY => [$anchor->copy(), $anchor->copy()],
            self::VIEW_MONTH => [$anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfMonth()->startOfDay()->endOfWeek(Carbon::SUNDAY)->startOfDay()],
            default => [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()],
        };
    }

    /**
     * The chips this user can use, with their state.
     *
     * @return list<array{key: string, label: string, color: string, on: bool}>
     */
    private function chips(): array
    {
        $user = $this->actor();
        $chips = [];

        foreach (self::CHIPS as $key => $chip) {
            if ($chip['permission'] !== null && ! $user->can($chip['permission']->value)) {
                continue;
            }

            $chips[] = [
                'key' => $key,
                'label' => $key === 'alert' ? __('calendar.alerts') : $chip['categories'][0]->getLabel(),
                'color' => $chip['categories'][0]->color(),
                'on' => ! in_array($key, $this->hidden, true),
            ];
        }

        return $chips;
    }

    /**
     * The categories left on, or null when no chip is off.
     *
     * @return ?list<CalendarCategory>
     */
    private function enabledCategories(): ?array
    {
        $off = [];

        foreach (self::CHIPS as $key => $chip) {
            if (in_array($key, $this->hidden, true)) {
                $off = [...$off, ...$chip['categories']];
            }
        }

        if ($off === []) {
            return null;
        }

        return array_values(array_filter(CalendarCategory::cases(), fn (CalendarCategory $category): bool => ! in_array($category, $off, true)));
    }

    /**
     * @param  list<CalendarEntry>  $entries
     * @return array<string, list<CalendarEntry>> the entries by day (`Y-m-d`)
     */
    private function byDay(array $entries): array
    {
        $days = [];

        foreach ($entries as $entry) {
            $days[$entry->date->toDateString()][] = $entry;
        }

        return $days;
    }

    /**
     * @param  list<CalendarEntry>  $entries  one day's entries, all-day first
     * @return Column
     */
    private function column(Carbon $day, array $entries): array
    {
        $today = Carbon::today();

        return [
            'date' => $day->copy(),
            'short' => (string) __('calendar.weekdays_short.'.$day->isoWeekday()),
            'number' => $day->day,
            'today' => $day->isSameDay($today),
            'weekend' => $day->isoWeekday() >= 6,
            'allDay' => array_values(array_filter($entries, fn (CalendarEntry $entry): bool => $entry->allDay || $entry->start === null)),
            'placed' => DayLayout::place($entries),
            'now' => $day->isSameDay($today) ? now()->hour * 60 + now()->minute : null,
        ];
    }

    private function dayLabel(Carbon $day): string
    {
        $text = (string) __('calendar.weekdays_long.'.$day->isoWeekday()).' '.$day->locale('tr')->isoFormat('D MMMM');

        return match (true) {
            $day->isSameDay(Carbon::today()) => __('calendar.today').' · '.$text,
            $day->isSameDay(Carbon::tomorrow()) => __('calendar.tomorrow').' · '.$text,
            default => $text,
        };
    }

    private function subtitle(Carbon $from, Carbon $to): string
    {
        $anchor = $this->anchor();

        return match ($this->viewName()) {
            self::VIEW_DAY => __('calendar.weekdays_long.'.$anchor->isoWeekday()).', '.$anchor->locale('tr')->isoFormat('D MMMM YYYY').($anchor->isSameDay(Carbon::today()) ? ' · '.__('calendar.today_suffix') : ''),
            self::VIEW_MONTH => $anchor->locale('tr')->isoFormat('MMMM YYYY'),
            default => $from->locale('tr')->isoFormat('D MMMM').' – '.$to->locale('tr')->isoFormat('D MMMM YYYY').' · '.__('calendar.week_number', ['number' => $from->isoWeek()]),
        };
    }

    /**
     * The event in the detail panel with the texts the panel shows, or null when nothing (valid) is selected.
     *
     * @return ?array{event: CalendarEvent, when: string, reminder: ?string, repeat: ?string}
     */
    private function detail(): ?array
    {
        $parts = explode(':', $this->selected);

        if (count($parts) !== 3 || $parts[0] !== 'event' || ! ctype_digit($parts[1])) {
            return null;
        }

        $day = $this->parseDate($parts[2]);
        $event = CalendarEvent::query()->with(['customer', 'attendees'])->find((int) $parts[1]);

        if ($day === null || $event === null) {
            return null;
        }

        $time = $event->all_day || $event->start_time === null
            ? __('calendar.all_day')
            : substr($event->start_time, 0, 5).($event->end_time !== null ? ' – '.substr($event->end_time, 0, 5) : '');

        $reminder = $event->remind_minutes === null ? null : match (true) {
            $event->remind_minutes % 1440 === 0 => __('calendar.detail.remind_days', ['count' => intdiv($event->remind_minutes, 1440)]),
            $event->remind_minutes % 60 === 0 => __('calendar.detail.remind_hours', ['count' => intdiv($event->remind_minutes, 60)]),
            default => __('calendar.detail.remind_minutes', ['count' => $event->remind_minutes]),
        };

        $repeat = null;

        if ($event->repeat !== EventRepeat::None) {
            $repeat = $event->repeat->getLabel().($event->repeat_until !== null
                ? ' · '.__('calendar.detail.repeat_until', ['until' => $event->repeat_until->format('d.m.Y')])
                : '');
        }

        return [
            'event' => $event,
            'when' => $day->format('d.m.Y').' · '.$time,
            'reminder' => $reminder,
            'repeat' => $repeat,
        ];
    }

    public function render(): View
    {
        $view = $this->viewName();
        [$from, $to] = $this->range();
        $anchor = $this->anchor();

        $entries = app(ListCalendarEntries::class)
            ->handle($this->actor(), $from, $to, $this->enabledCategories(), $this->mine)
            ->values()
            ->all();
        $byDay = $this->byDay($entries);

        $columns = [];
        $cells = [];
        $groups = [];
        $strip = [];
        $records = [];

        if ($view === self::VIEW_WEEK || $view === self::VIEW_DAY) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $columns[] = $this->column($day, $byDay[$day->toDateString()] ?? []);
            }
        }

        if ($view === self::VIEW_MONTH) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $dayEntries = $byDay[$day->toDateString()] ?? [];

                $cells[] = [
                    'date' => $day->copy(),
                    'label' => $day->day === 1 ? $day->locale('tr')->isoFormat('D MMM') : (string) $day->day,
                    'inMonth' => $day->month === $anchor->month,
                    'today' => $day->isSameDay(Carbon::today()),
                    'weekend' => $day->isoWeekday() >= 6,
                    'shown' => array_slice($dayEntries, 0, self::MONTH_CELL_LIMIT),
                    'more' => max(count($dayEntries) - self::MONTH_CELL_LIMIT, 0),
                ];
            }
        }

        if ($view !== self::VIEW_MONTH) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $dayEntries = $byDay[$day->toDateString()] ?? [];

                $strip[] = [
                    'date' => $day->copy(),
                    'min' => (string) __('calendar.weekdays_min.'.$day->isoWeekday()),
                    'number' => $day->day,
                    'today' => $day->isSameDay(Carbon::today()),
                    'picked' => $day->isSameDay($anchor),
                    'has' => $dayEntries !== [],
                ];

                if ($dayEntries !== []) {
                    $groups[] = ['date' => $day->copy(), 'label' => $this->dayLabel($day), 'today' => $day->isSameDay(Carbon::today()), 'entries' => $dayEntries];
                }
            }
        }

        if ($view === self::VIEW_DAY) {
            $records = array_values(array_filter($byDay[$anchor->toDateString()] ?? [], fn (CalendarEntry $entry): bool => $entry->recorded));
        }

        $mini = [];

        if ($view === self::VIEW_DAY) {
            $month = $this->miniAnchor();

            $miniEnd = $month->copy()->endOfMonth()->startOfDay()->endOfWeek(Carbon::SUNDAY);

            for ($day = $month->copy()->startOfWeek(Carbon::MONDAY); $day->lte($miniEnd); $day->addDay()) {
                $mini[] = [
                    'date' => $day->copy(),
                    'number' => $day->day,
                    'inMonth' => $day->month === $month->month,
                    'picked' => $day->isSameDay($anchor),
                ];
            }
        }

        return view('livewire.pages.calendar.index', [
            'viewName' => $view,
            'views' => [
                self::VIEW_DAY => __('calendar.views.day'),
                self::VIEW_WEEK => __('calendar.views.week'),
                self::VIEW_MONTH => __('calendar.views.month'),
                self::VIEW_LIST => __('calendar.views.list'),
            ],
            'subtitle' => $this->subtitle($from, $to),
            'chips' => $this->chips(),
            'columns' => $columns,
            'cells' => $cells,
            'groups' => $groups,
            'strip' => $strip,
            'records' => $records,
            'mini' => $mini,
            'miniTitle' => $this->miniAnchor()->locale('tr')->isoFormat('MMMM YYYY'),
            'detail' => $view === self::VIEW_DAY ? $this->detail() : null,
            'anchor' => $anchor,
            'canManage' => $this->actor()->can(Permission::CalendarManage->value),
            'empty' => $entries === [],
        ])->title(__('calendar.title'));
    }

    protected function requiredPermission(): ?Permission
    {
        return null;
    }
}
