<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case Try = 'TRY';
    case Usd = 'USD';
    case Eur = 'EUR';

    public function getLabel(): string
    {
        return __('enums.currency.'.$this->value);
    }
}
