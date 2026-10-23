<?php

namespace App\Filament\Resources\CatalogServices;

use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\CatalogServices\Schemas\CatalogServiceForm;
use App\Filament\Resources\CatalogServices\Tables\CatalogServicesTable;
use App\Models\CatalogService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CatalogServiceResource extends Resource
{
    protected static ?string $model = CatalogService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Ctrl+K searches customers only (docs/UI.md §2 Kural 12, §7); catalog items keep their
     * own list's search.
     */
    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): string
    {
        return __('navigation.catalog');
    }

    public static function getModelLabel(): string
    {
        return __('catalog_services.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog_services.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return CatalogServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogServicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCatalogServices::route('/'),
        ];
    }
}
