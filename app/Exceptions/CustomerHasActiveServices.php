<?php

namespace App\Exceptions;

use DomainException;

class CustomerHasActiveServices extends DomainException
{
    public function __construct()
    {
        parent::__construct(__('customers.has_active_services'));
    }
}
