<?php

namespace App\Filament\Resources\Quotes;

use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\Tables\QuotesTable;
use App\Models\Quote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * All quotes, newest first (Faz 6, docs/UI.md §5 Teklifler). Quotes are made from the catalog or
 * from "Yeni teklif" and handled in windows, so there is only the list page.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = false;

    public static function getNavigationLabel(): string
    {
        return __('navigation.quotes');
    }

    public static function getModelLabel(): string
    {
        return __('quotes.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('quotes.plural');
    }

    public static function table(Table $table): Table
    {
        return QuotesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
        ];
    }
}
