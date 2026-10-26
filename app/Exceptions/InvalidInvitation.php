<?php

namespace App\Exceptions;

use DomainException;

/**
 * The invitation cannot be used: expired, replaced by a newer one, already accepted or the user is deactivated.
 */
class InvalidInvitation extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('users.invitation_invalid'));
    }
}
