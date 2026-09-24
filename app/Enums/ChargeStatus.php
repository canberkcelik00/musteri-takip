<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChargeStatus: string implements HasLabel
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('enums.charge_status.'.$this->value);
    }
}
