<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ServerType: string implements HasLabel
{
    case Whm = 'whm';
    case Cpanel = 'cpanel';

    public function getLabel(): string
    {
        return __('enums.server_type.'.$this->value);
    }

    public function defaultPort(): int
    {
        return match ($this) {
            self::Whm => 2087,
            self::Cpanel => 2083,
        };
    }
}
