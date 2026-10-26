<?php

namespace App\Exceptions;

use DomainException;

/**
 * A role change or deactivation the rules forbid (own account, last active administrator).
 */
class InvalidUserChange extends DomainException
{
    public static function ownAccount(): self
    {
        return new self(__('users.own_account'));
    }

    public static function lastAdministrator(): self
    {
        return new self(__('users.last_administrator'));
    }
}
