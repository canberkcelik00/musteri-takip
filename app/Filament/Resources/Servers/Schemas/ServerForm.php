<?php

namespace App\Filament\Resources\Servers\Schemas;

use App\Actions\Servers\DeleteServer;
use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Filament\Actions\ArchiveAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Support\RecordActionsSection;
use App\Models\CatalogService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('servers.type'))
                    ->options(ServerType::class)
                    ->default(ServerType::Whm->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $type = self::resolveType($state);

                        if ($type !== null) {
                            $set('port', $type->defaultPort());
                        }
                    }),
                TextInput::make('name')
                    ->label(__('servers.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('hostname')
                    ->label(__('servers.hostname'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('port')
                    ->label(__('servers.port'))
                    ->default(ServerType::Whm->defaultPort())
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535),
                TextInput::make('username')
                    ->label(__('servers.username'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('api_token')
                    ->label(__('servers.api_token'))
                    ->helperText(__('servers.api_token_helper'))
                    ->password()
                    ->autocomplete(false)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('catalog_service_id')
                    ->label(__('servers.catalog_service'))
                    ->helperText(__('servers.catalog_service_helper'))
                    ->options(fn (): array => CatalogService::query()
                        ->notArchived()
                        ->where('type', ServiceType::Hosting)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => self::resolveType($get('type')) === ServerType::Cpanel)
                    ->required(fn (Get $get): bool => self::resolveType($get('type')) === ServerType::Cpanel)
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn (Get $get, mixed $state): mixed => self::resolveType($get('type')) === ServerType::Cpanel ? $state : null),
                RecordActionsSection::make(
                    __('servers.actions_section'),
                    [ArchiveAction::make(), UnarchiveAction::make()],
                    [DeleteRecordAction::make()->deleteWith(DeleteServer::class)],
                ),
            ]);
    }

    /**
     * The form state holds the enum on the edit page (model cast) and its value after a selection.
     */
    private static function resolveType(mixed $state): ?ServerType
    {
        return $state instanceof ServerType ? $state : ServerType::tryFrom((string) $state);
    }
}
