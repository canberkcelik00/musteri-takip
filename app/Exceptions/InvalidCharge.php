<?php

namespace App\Exceptions;

use App\Enums\ChargeErrorReason;
use DomainException;

class InvalidCharge extends DomainException
{
    public function __construct(string $message, public readonly ChargeErrorReason $reason)
    {
        parent::__construct($message);
    }

    public static function customerServiceOfAnotherCustomer(): self
    {
        return new self(__('charges.customer_service_of_another_customer'), ChargeErrorReason::CustomerServiceOfAnotherCustomer);
    }

    public static function notOpen(): self
    {
        return new self(__('charges.not_open'), ChargeErrorReason::NotOpen);
    }

    public static function hasAllocations(): self
    {
        return new self(__('charges.has_allocations'), ChargeErrorReason::HasAllocations);
    }

    public static function amountNotPositive(): self
    {
        return new self(__('charges.amount_not_positive'), ChargeErrorReason::AmountNotPositive);
    }

    public static function descriptionRequired(): self
    {
        return new self(__('charges.description_required'), ChargeErrorReason::DescriptionRequired);
    }

    public static function invalidSplitAmount(): self
    {
        return new self(__('charges.invalid_split_amount'), ChargeErrorReason::InvalidSplitAmount);
    }

    public static function cancelReasonRequired(): self
    {
        return new self(__('charges.cancel_reason_required'), ChargeErrorReason::CancelReasonRequired);
    }

    public static function cannotCancelWithAllocations(): self
    {
        return new self(__('charges.cannot_cancel_with_allocations'), ChargeErrorReason::CannotCancelWithAllocations);
    }
}
