<?php

namespace App\Livewire\Windows;

use App\Actions\Calendar\CreateCalendarEvent;
use App\Actions\Calendar\UpdateCalendarEvent;
use App\Enums\EventRepeat;
use App\Exceptions\CalendarMailNotSent;
use App\Exceptions\InvalidCalendarEvent;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CalendarEvent;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a calendar event (docs/UI.md §5 "Etkinlik penceresi"). Every change goes through the Actions in
 * App\Actions\Calendar; a change to a repeating event applies to its whole series. Deleting is not here: the detail
 * panel of the calendar asks for it in the confirm window. When the invite e-mail fails the event stays saved and a
 * yellow notice says so.
 */
class EditCalendarEvent extends Component
{
    use AuthorizesPermission;

    /** The reminder choices (minutes before the start). */
    public const array REMINDERS = [0, 5, 10, 15, 30, 60, 120, 1440];

    /** The duration pills (minutes). */
    public const array DURATIONS = [30, 60, 120];

    #[Locked]
    public ?int $eventId = null;

    public string $title = '';

    public string $date = '';

    public string $startTime = '';

    public string $endTime = '';

    public bool $allDay = false;

    public string $customerId = '';

    public string $location = '';

    /** @var list<string> */
    public array $attendeeIds = [];

    public string $remindMinutes = '';

    public string $repeat = 'none';

    public string $repeatUntil = '';

    public string $notes = '';

    /**
     * A new event opens on the given day and hour (the slot that was clicked), or on the next full hour today; it lasts
     * one hour. An existing event opens with its own values.
     */
    public function mount(?int $event = null, ?string $date = null, ?string $time = null, ?int $customer = null): void
    {
        if ($event !== null) {
            $this->fillFrom(CalendarEvent::query()->with('attendees')->findOrFail($event));

            return;
        }

        $this->date = $this->validDate($date) ?? today()->format('Y-m-d');
        $this->startTime = $this->validTime($time) ?? $this->nextFullHour();
        $this->endTime = $this->addMinutes($this->startTime, 60) ?? '';
        $this->customerId = $customer !== null ? (string) Customer::query()->findOrFail($customer)->id : '';
        $this->attendeeIds = [(string) $this->actor()->id];
    }

    public function save(CreateCalendarEvent $createEvent, UpdateCalendarEvent $updateEvent): void
    {
        $this->validate();

        $attributes = [
            'title' => trim($this->title),
            'starts_on' => $this->date,
            'start_time' => $this->allDay ? null : $this->startTime,
            'end_time' => $this->allDay || $this->endTime === '' ? null : $this->endTime,
            'all_day' => $this->allDay,
            'customer_id' => $this->customerId === '' ? null : (int) $this->customerId,
            'location' => trim($this->location),
            'notes' => $this->notes,
            'remind_minutes' => $this->remindMinutes === '' ? null : (int) $this->remindMinutes,
            'repeat' => $this->repeat,
            'repeat_until' => $this->repeat === EventRepeat::None->value || $this->repeatUntil === '' ? null : $this->repeatUntil,
        ];
        $attendees = array_map('intval', $this->attendeeIds);

        try {
            if ($this->eventId === null) {
                $createEvent->handle($this->actor(), $attributes, $attendees);
            } else {
                $updateEvent->handle($this->actor(), CalendarEvent::query()->findOrFail($this->eventId), $attributes, $attendees);
            }
        } catch (InvalidCalendarEvent $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        } catch (CalendarMailNotSent $exception) {
            $this->dispatch('calendar-changed');
            $this->dispatch('close-window');
            $this->dispatch('notify', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch('calendar-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('calendar.saved'));
    }

    /**
     * A duration pill: the end becomes the start plus the minutes; anything else (or no start) changes nothing.
     */
    public function pickDuration(int $minutes): void
    {
        if (! in_array($minutes, self::DURATIONS, true)) {
            return;
        }

        $end = $this->addMinutes($this->startTime, $minutes);

        if ($end !== null) {
            $this->endTime = $end;
        }
    }

    /**
     * An attendee is added or taken off by clicking their name.
     */
    public function toggleAttendee(string $id): void
    {
        $this->attendeeIds = in_array($id, $this->attendeeIds, true)
            ? array_values(array_diff($this->attendeeIds, [$id]))
            : [...$this->attendeeIds, $id];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'allDay' => ['boolean'],
            'customerId' => ['nullable', 'integer', 'exists:customers,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'attendeeIds' => ['array'],
            'attendeeIds.*' => ['integer', 'exists:users,id'],
            'remindMinutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'repeat' => ['required', Rule::enum(EventRepeat::class)],
            'repeatUntil' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'title' => __('calendar.form.title'),
            'date' => __('calendar.form.date'),
            'startTime' => __('calendar.form.start'),
            'endTime' => __('calendar.form.end'),
            'customerId' => __('calendar.form.customer'),
            'location' => __('calendar.form.location'),
            'attendeeIds' => __('calendar.form.attendees'),
            'remindMinutes' => __('calendar.form.reminder'),
            'repeat' => __('calendar.form.repeat'),
            'repeatUntil' => __('calendar.form.repeat_until'),
            'notes' => __('calendar.form.notes'),
        ];
    }

    private function fillFrom(CalendarEvent $record): void
    {
        $this->eventId = $record->id;
        $this->title = $record->title;
        $this->date = $record->starts_on->format('Y-m-d');
        $this->allDay = $record->all_day;
        $this->startTime = (string) ($record->start_time === null ? '' : substr($record->start_time, 0, 5));
        $this->endTime = (string) ($record->end_time === null ? '' : substr($record->end_time, 0, 5));
        $this->customerId = (string) $record->customer_id;
        $this->location = (string) $record->location;
        $this->attendeeIds = $record->attendees->map(fn (User $user): string => (string) $user->id)->all();
        $this->remindMinutes = $record->remind_minutes === null ? '' : (string) $record->remind_minutes;
        $this->repeat = $record->repeat->value;
        $this->repeatUntil = (string) $record->repeat_until?->format('Y-m-d');
        $this->notes = (string) $record->notes;
    }

    private function validDate(?string $value): ?string
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $day = Carbon::createFromFormat('!Y-m-d', $value);

        return $day instanceof Carbon && $day->format('Y-m-d') === $value ? $value : null;
    }

    private function validTime(?string $value): ?string
    {
        return $value !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : null;
    }

    /**
     * The next full hour, held at 23:00 late in the evening so the default stays on the same day.
     */
    private function nextFullHour(): string
    {
        return sprintf('%02d:00', min(now()->hour + 1, 23));
    }

    /**
     * `$time` plus the minutes as `H:i`, or null when there is no (valid) time or the result leaves the day.
     */
    private function addMinutes(string $time, int $minutes): ?string
    {
        if ($this->validTime($time) === null) {
            return null;
        }

        $total = (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2) + $minutes;

        return $total >= 1440 ? null : sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public function render(): View
    {
        $record = $this->eventId === null ? null : CalendarEvent::query()->with('attendees')->findOrFail($this->eventId);
        $attendingIds = $record?->attendees->modelKeys() ?? [];

        // Active users can be picked; the ones already on the event stay listed.
        $people = User::query()->whereNull('deactivated_at')->orWhereIn('id', $attendingIds)->orderBy('name')->get()
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => $user->name.($user->isActive() ? '' : ' '.__('tasks.inactive_suffix'))])->all();
        $customers = Customer::query()->notArchived()->orWhere('id', $record?->customer_id)->orderBy('short_name')->pluck('short_name', 'id')->all();

        $reminderMinutes = self::REMINDERS;

        if ($record?->remind_minutes !== null && ! in_array($record->remind_minutes, $reminderMinutes, true)) {
            $reminderMinutes[] = $record->remind_minutes;
            sort($reminderMinutes);
        }

        $reminders = ['' => __('calendar.form.no_reminder')];

        foreach ($reminderMinutes as $minutes) {
            $reminders[(string) $minutes] = match (true) {
                $minutes === 0 => __('calendar.form.remind_at_start'),
                $minutes % 1440 === 0 => __('calendar.detail.remind_days', ['count' => intdiv($minutes, 1440)]),
                $minutes % 60 === 0 => __('calendar.detail.remind_hours', ['count' => intdiv($minutes, 60)]),
                default => __('calendar.detail.remind_minutes', ['count' => $minutes]),
            };
        }

        return view('livewire.windows.edit-calendar-event', [
            'record' => $record,
            'people' => $people,
            'customers' => ['' => __('calendar.form.no_customer')] + $customers,
            'reminders' => $reminders,
            'repeats' => collect(EventRepeat::cases())->mapWithKeys(fn (EventRepeat $repeat): array => [$repeat->value => $repeat->getLabel()])->all(),
            'durations' => collect(self::DURATIONS)->mapWithKeys(fn (int $minutes): array => [$minutes => $this->endTime !== '' && $this->addMinutes($this->startTime, $minutes) === $this->endTime])->all(),
        ]);
    }
}
