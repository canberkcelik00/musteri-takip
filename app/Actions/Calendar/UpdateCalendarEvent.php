<?php

namespace App\Actions\Calendar;

use App\Enums\EventRepeat;
use App\Exceptions\CalendarMailNotSent;
use App\Exceptions\InvalidCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCalendarEvent
{
    public function __construct(
        private NormalizeCalendarEventAttributes $normalize,
        private SendCalendarEventInvites $sendInvites,
    ) {}

    /**
     * Change an event; the change applies to the whole series. Only attendees added by this change are checked (one may
     * stay on an event deactivated since) and, when not `$actor`, e-mailed; a failed e-mail leaves the change saved.
     *
     * @param  array{title: string, starts_on: mixed, start_time?: ?string, end_time?: ?string, all_day?: bool, customer_id?: ?int, location?: ?string, notes?: ?string, remind_minutes?: ?int, repeat?: EventRepeat|string|null, repeat_until?: mixed}  $attributes
     * @param  list<int>  $attendeeIds
     *
     * @throws InvalidCalendarEvent
     * @throws CalendarMailNotSent
     */
    public function handle(User $actor, CalendarEvent $event, array $attributes, array $attendeeIds = []): CalendarEvent
    {
        $values = $this->normalize->handle($attributes, $event);

        $currentIds = $event->attendees()->pluck('users.id')->all();
        $added = User::query()->findMany(array_diff(array_unique($attendeeIds), $currentIds));

        if ($added->contains(fn (User $attendee): bool => ! $attendee->isActive())) {
            throw InvalidCalendarEvent::attendeeInactive();
        }

        DB::transaction(function () use ($event, $values, $attendeeIds): void {
            $event->fill($values)->save();
            $event->attendees()->sync(array_unique($attendeeIds));
        });

        $this->sendInvites->handle($event, $actor, $added);

        return $event;
    }
}
