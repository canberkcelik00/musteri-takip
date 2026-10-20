<?php

namespace App\Filament\Resources\Servers\Tables;

use App\Actions\Servers\TestServerConnection;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse']))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label(__('servers.name'))
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label(__('servers.type'))
                    ->visibleFrom('md'),
                TextColumn::make('hostname')
                    ->label(__('servers.hostname'))
                    ->visibleFrom('lg'),
                TextColumn::make('last_pulled_at')
                    ->label(__('servers.last_pulled_at'))
                    ->date('d.m.Y')
                    ->placeholder(__('servers.never_pulled'))
                    ->visibleFrom('md'),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Server $record): ?string => $record->isArchived() ? __('archive.status_archived') : null),
            ])
            ->filters([
                ArchivedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('testConnection')
                    ->label(__('servers.test_connection'))
                    ->color('gray')
                    ->action(function (Server $record): void {
                        $result = app(TestServerConnection::class)->handle($record);

                        $notification = Notification::make()->title($result->message);

                        $result->successful ? $notification->success()->send() : $notification->danger()->send();
                    }),
            ])
            ->emptyStateHeading(__('servers.empty_heading'))
            ->emptyStateDescription(__('servers.empty_description'))
            ->emptyStateActions([
                CreateAction::make('createEmpty')->label(__('servers.add'))->color('gray'),
            ]);
    }
}
