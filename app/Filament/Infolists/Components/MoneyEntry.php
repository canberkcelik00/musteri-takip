<?php

namespace App\Filament\Infolists\Components;

use App\Filament\Concerns\FormatsMoney;
use Filament\Infolists\Components\TextEntry;

class MoneyEntry extends TextEntry
{
    use FormatsMoney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMoneyFormatting();
    }
}
