<?php

namespace App\Filament\Tables\Columns;

use App\Filament\Concerns\FormatsMoney;
use Filament\Tables\Columns\TextColumn;

class MoneyColumn extends TextColumn
{
    use FormatsMoney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMoneyFormatting();
        $this->alignEnd();
    }
}
