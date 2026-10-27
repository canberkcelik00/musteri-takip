<?php

namespace App\Actions\Calendar;

use App\Exceptions\CalendarMailNotSent;
use App\Mail\CalendarEventInviteMail;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCalendarEventInvites
{
    /**
     * E-mail each of `$attendees` that `$invitedBy` added them to the event, except `$invitedBy` themself. The event is
     * already saved when this runs. Every recipient is tried even when one fails; the failures are raised together.
     *
     * @param  Collection<int, User>  $attendees
     *
     * @throws CalendarMailNotSent
     */
    public function handle(CalendarEvent $event, User $invitedBy, Collection $attendees): void
    {
        $event->loadMissing('customer');
        $failures = [];

        foreach ($attendees->reject(fn (User $attendee): bool => $attendee->is($invitedBy)) as $attendee) {
            try {
                Mail::to($attendee->email)->send(new CalendarEventInviteMail($event, $attendee, $invitedBy));
            } catch (Throwable $exception) {
                $failures[] = $attendee->email.': '.$exception->getMessage();
            }
        }

        if ($failures !== []) {
            throw new CalendarMailNotSent($failures);
        }
    }
}
