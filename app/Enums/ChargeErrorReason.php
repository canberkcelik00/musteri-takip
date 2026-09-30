<?php

namespace App\Enums;

enum ChargeErrorReason: string
{
    case CustomerServiceOfAnotherCustomer = 'customer_service_of_another_customer';
    case NotOpen = 'not_open';
    case HasAllocations = 'has_allocations';
    case AmountNotPositive = 'amount_not_positive';
    case DescriptionRequired = 'description_required';
    case InvalidSplitAmount = 'invalid_split_amount';
    case CancelReasonRequired = 'cancel_reason_required';
    case CannotCancelWithAllocations = 'cannot_cancel_with_allocations';
}
