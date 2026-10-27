<?php

namespace App\Actions\Calendar;

use App\Models\CalendarEvent;

class DeleteCalendarEvent
{
    /**
     * Delete the event and, with it, its whole series, its attendee links and its sent-reminder rows.
     */
    public function handle(CalendarEvent $event): void
    {
        $event->delete();
    }
}
