<?php

namespace App\Exceptions;

use DomainException;

class LastActivePaymentMethod extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('payment_methods.last_active'));
    }
}
