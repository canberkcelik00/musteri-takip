<?php

namespace App\Filament\Resources\Packages\Schemas;

use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\CatalogService;
use App\Models\Package;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('packages.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('catalogServices')
                            ->label(__('packages.catalog_services'))
                            ->helperText(__('packages.catalog_services_helper'))
                            ->relationship(
                                name: 'catalogServices',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, ?Package $record): Builder => self::selectableCatalogServices($query, $record),
                            )
                            ->getOptionLabelFromRecordUsing(fn (CatalogService $record): string => $record->isArchived()
                                ? __('packages.archived_option', ['name' => $record->name])
                                : $record->name)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required()
                            ->minItems(2)
                            ->columnSpanFull(),
                        MoneyInput::make('price')
                            ->label(__('packages.price'))
                            ->required()
                            ->currencyField(),
                        CurrencySelect::make('currency'),
                        Textarea::make('notes')
                            ->label(__('packages.notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Not archived catalog services, plus the archived ones the package already contains so that
     * saving the package does not silently drop them.
     *
     * @param  Builder<CatalogService>  $query
     * @return Builder<CatalogService>
     */
    private static function selectableCatalogServices(Builder $query, ?Package $record): Builder
    {
        return $query->where(function (Builder $query) use ($record): void {
            $query->notArchived();

            if ($record !== null) {
                $query->orWhereIn('catalog_services.id', $record->catalogServices()->select('catalog_services.id'));
            }
        });
    }
}
