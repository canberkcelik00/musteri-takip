<?php

namespace App\Enums;

enum ChargeStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('enums.charge_status.'.$this->value);
    }
}
