<?php

namespace App\Exceptions;

use DomainException;

class InvalidCustomerService extends DomainException
{
    public static function priceNotPositive(): self
    {
        return new self(__('customer_services.price_not_positive'));
    }

    public static function itemNotInSoldService(): self
    {
        return new self(__('customer_services.item_not_in_sold_service'));
    }
}
