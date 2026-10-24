<?php

namespace App\Enums;

enum CustomerServiceStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('enums.customer_service_status.'.$this->value);
    }
}
