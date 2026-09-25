<?php

namespace App\Filament\Resources\CatalogServices\Schemas;

use App\Enums\ServiceType;
use App\Filament\Forms\Components\CurrencySelect;
use App\Filament\Forms\Components\MoneyInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CatalogServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label(__('catalog_services.type'))
                            ->options(ServiceType::class)
                            ->required()
                            ->live(),
                        TextInput::make('name')
                            ->label(__('catalog_services.name'))
                            ->required()
                            ->maxLength(255),
                        MoneyInput::make('price')
                            ->label(__('catalog_services.price'))
                            ->required()
                            ->currencyField(),
                        CurrencySelect::make('currency'),
                        Textarea::make('notes')
                            ->label(__('catalog_services.notes'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('catalog_services.details_section'))
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::resolveType($get('type')) !== null)
                    ->schema(fn (Get $get): array => self::detailFields(self::resolveType($get('type')))),
            ]);
    }

    /**
     * The form state holds the enum on the edit page (model cast) and its value after a selection.
     */
    private static function resolveType(mixed $state): ?ServiceType
    {
        return $state instanceof ServiceType ? $state : ServiceType::tryFrom((string) $state);
    }

    /**
     * Free-text inputs for the catalog details keys of the given type.
     *
     * @return list<TextInput>
     */
    private static function detailFields(?ServiceType $type): array
    {
        if ($type === null) {
            return [];
        }

        return array_map(
            fn (string $key): TextInput => TextInput::make("details.{$key}")
                ->label($type->catalogDetailLabel($key))
                ->maxLength(255),
            $type->catalogDetailKeys(),
        );
    }
}
