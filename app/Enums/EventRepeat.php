<?php

namespace App\Enums;

enum EventRepeat: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return __('enums.event_repeat.'.$this->value);
    }
}
