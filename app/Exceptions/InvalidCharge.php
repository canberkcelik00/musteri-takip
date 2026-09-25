<?php

namespace App\Exceptions;

use DomainException;

class InvalidCharge extends DomainException
{
    public static function customerServiceOfAnotherCustomer(): self
    {
        return new self(__('charges.customer_service_of_another_customer'));
    }

    public static function notOpen(): self
    {
        return new self(__('charges.not_open'));
    }

    public static function hasAllocations(): self
    {
        return new self(__('charges.has_allocations'));
    }

    public static function amountNotPositive(): self
    {
        return new self(__('charges.amount_not_positive'));
    }

    public static function descriptionRequired(): self
    {
        return new self(__('charges.description_required'));
    }

    public static function invalidSplitAmount(): self
    {
        return new self(__('charges.invalid_split_amount'));
    }

    public static function cancelReasonRequired(): self
    {
        return new self(__('charges.cancel_reason_required'));
    }

    public static function cannotCancelWithAllocations(): self
    {
        return new self(__('charges.cannot_cancel_with_allocations'));
    }
}
