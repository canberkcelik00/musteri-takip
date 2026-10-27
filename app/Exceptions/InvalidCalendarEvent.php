<?php

namespace App\Exceptions;

use DomainException;

class InvalidCalendarEvent extends DomainException
{
    public static function startTimeRequired(): self
    {
        return new self(__('calendar.start_time_required'));
    }

    public static function endNotAfterStart(): self
    {
        return new self(__('calendar.end_not_after_start'));
    }

    public static function repeatUntilBeforeStart(): self
    {
        return new self(__('calendar.repeat_until_before_start'));
    }

    public static function attendeeInactive(): self
    {
        return new self(__('calendar.attendee_inactive'));
    }

    public static function customerArchived(): self
    {
        return new self(__('calendar.customer_archived'));
    }
}
