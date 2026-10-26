<?php

namespace App\Exceptions;

use DomainException;

class InvalidTask extends DomainException
{
    public static function assigneeInactive(): self
    {
        return new self(__('tasks.assignee_inactive'));
    }

    public static function customerArchived(): self
    {
        return new self(__('tasks.customer_archived'));
    }
}
