<?php

namespace App\Exceptions;

use DomainException;

class PaymentCannotBeUndone extends DomainException
{
    public static function notAvailable(): self
    {
        return new self(__('payments.undo_unavailable'));
    }
}
