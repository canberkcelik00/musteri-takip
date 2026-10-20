<?php

namespace App\Filament\Resources\Servers;

use App\Filament\Resources\Servers\Pages\ManageServers;
use App\Filament\Resources\Servers\Schemas\ServerForm;
use App\Filament\Resources\Servers\Tables\ServersTable;
use App\Models\Server;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Ctrl+K searches customers only (docs/UI.md §2 Kural 12, §7).
     */
    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): string
    {
        return __('navigation.settings');
    }

    public static function getModelLabel(): string
    {
        return __('servers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('servers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServers::route('/'),
        ];
    }
}
