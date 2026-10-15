<?php

namespace App\Exceptions;

use DomainException;

class ReminderNotSent extends DomainException
{
    public static function notEligible(): self
    {
        return new self(__('reminders.not_eligible'));
    }

    public static function noRecipient(): self
    {
        return new self(__('reminders.no_recipient'));
    }
}
