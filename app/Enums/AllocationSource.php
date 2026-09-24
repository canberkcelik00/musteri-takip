<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a payment allocation came from: made while the payment was recorded,
 * or applied later from the customer's credit balance to a new charge.
 */
enum AllocationSource: string implements HasLabel
{
    case Payment = 'payment';
    case CreditBalance = 'credit_balance';

    public function getLabel(): string
    {
        return __('enums.allocation_source.'.$this->value);
    }
}
