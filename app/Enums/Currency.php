<?php

namespace App\Enums;

enum Currency: string
{
    case Try = 'TRY';
    case Usd = 'USD';
    case Eur = 'EUR';

    public function getLabel(): string
    {
        return __('enums.currency.'.$this->value);
    }
}
