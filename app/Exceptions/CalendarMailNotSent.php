<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An "event invitation" e-mail could not be sent; the event is saved all the same.
 */
class CalendarMailNotSent extends RuntimeException
{
    /**
     * @param  list<string>  $messages  What went wrong, one entry per attendee that could not be e-mailed.
     */
    public function __construct(array $messages)
    {
        parent::__construct(__('calendar.mail_not_sent', ['message' => implode('; ', $messages)]));
    }
}
