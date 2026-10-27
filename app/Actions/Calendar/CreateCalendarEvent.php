<?php

namespace App\Actions\Calendar;

use App\Enums\EventRepeat;
use App\Exceptions\CalendarMailNotSent;
use App\Exceptions\InvalidCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCalendarEvent
{
    public function __construct(
        private NormalizeCalendarEventAttributes $normalize,
        private SendCalendarEventInvites $sendInvites,
    ) {}

    /**
     * Create an event. Each attendee other than `$actor` is e-mailed, but a failed e-mail leaves the event saved.
     *
     * @param  array{title: string, starts_on: mixed, start_time?: ?string, end_time?: ?string, all_day?: bool, customer_id?: ?int, location?: ?string, notes?: ?string, remind_minutes?: ?int, repeat?: EventRepeat|string|null, repeat_until?: mixed}  $attributes
     * @param  list<int>  $attendeeIds
     *
     * @throws InvalidCalendarEvent
     * @throws CalendarMailNotSent
     */
    public function handle(User $actor, array $attributes, array $attendeeIds = []): CalendarEvent
    {
        $values = $this->normalize->handle($attributes);

        $attendees = User::query()->findMany(array_unique($attendeeIds));

        if ($attendees->contains(fn (User $attendee): bool => ! $attendee->isActive())) {
            throw InvalidCalendarEvent::attendeeInactive();
        }

        $event = DB::transaction(function () use ($actor, $values, $attendees): CalendarEvent {
            $event = new CalendarEvent($values);
            $event->forceFill(['created_by' => $actor->id])->save();
            $event->attendees()->sync($attendees->modelKeys());

            return $event;
        });

        $this->sendInvites->handle($event, $actor, $attendees);

        return $event;
    }
}
