<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The invitation e-mail could not be sent; the user is saved all the same and can be invited again.
 */
class InvitationNotSent extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(__('users.invitation_not_sent', ['message' => $previous->getMessage()]), 0, $previous);
    }
}
