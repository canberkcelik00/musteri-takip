<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomerServiceStatus: string implements HasLabel
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('enums.customer_service_status.'.$this->value);
    }
}
