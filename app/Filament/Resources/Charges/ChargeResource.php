<?php

namespace App\Filament\Resources\Charges;

use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Charges\Tables\ChargesTable;
use App\Models\Charge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * All customers' charges. Charges are created by rules or by hand and then edited, split or
 * cancelled from the row actions, so there is only the list page.
 */
class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('charges.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('charges.plural');
    }

    public static function table(Table $table): Table
    {
        return ChargesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharges::route('/'),
        ];
    }
}
