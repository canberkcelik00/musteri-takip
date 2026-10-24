<?php

namespace App\Enums;

/**
 * Where a payment allocation came from: made while the payment was recorded,
 * or applied later from the customer's credit balance to a new charge.
 */
enum AllocationSource: string
{
    case Payment = 'payment';
    case CreditBalance = 'credit_balance';

    public function getLabel(): string
    {
        return __('enums.allocation_source.'.$this->value);
    }
}
