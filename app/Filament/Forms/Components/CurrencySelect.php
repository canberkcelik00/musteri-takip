<?php

namespace App\Filament\Forms\Components;

use App\Enums\Currency;
use Filament\Forms\Components\Select;

/**
 * Currency choice (TL, USD, EUR) that defaults to TL.
 */
class CurrencySelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('money.currency'))
            ->options(Currency::class)
            ->default(Currency::Try->value)
            ->required()
            ->selectablePlaceholder(false)
            ->live();
    }
}
