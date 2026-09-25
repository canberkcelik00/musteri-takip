<?php

namespace App\Exceptions;

use DomainException;

class InvalidCharge extends DomainException
{
    public static function customerServiceOfAnotherCustomer(): self
    {
        return new self(__('charges.customer_service_of_another_customer'));
    }
}
