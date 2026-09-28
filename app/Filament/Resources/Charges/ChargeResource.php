<?php

namespace App\Filament\Resources\Charges;

use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Charges\Tables\ChargesTable;
use App\Models\Charge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * All customers' charges. Charges are created by rules or by hand and then edited, split or
 * cancelled from the row actions, so there is only the list page. Menu label "Açık borçlar":
 * the list opens with the default "Açık" status filter (docs/UI.md §3), so the label matches
 * what the user sees, even though the resource itself covers every status.
 */
class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string
    {
        return __('navigation.collections');
    }

    public static function getNavigationLabel(): string
    {
        return __('navigation.open_charges');
    }

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
