<?php

namespace App\Filament\Resources\Servers\Tables;

use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\TestServerConnection;
use App\Filament\Actions\PullFromServerAction;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\AccountUsage;
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
        $warningsByServer = null;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse'])->with('accountUsages'))
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
                TextColumn::make('usage')
                    ->label(__('usage.servers_column'))
                    ->visibleFrom('md')
                    ->state(function (Server $record) use (&$warningsByServer): string {
                        $failed = $record->accountUsages->first(fn (AccountUsage $usage): bool => $usage->failure_reason !== null);

                        if ($failed !== null) {
                            return __('usage.servers_column_failed', ['reason' => __('usage.failure.'.$failed->failure_reason->value)]);
                        }

                        $refreshedAt = $record->accountUsages->max('refreshed_at');

                        if ($refreshedAt === null) {
                            return __('usage.never_read');
                        }

                        $warningsByServer ??= self::warningsByServer();
                        $warnings = $warningsByServer[$record->id] ?? 0;

                        return $refreshedAt->format('d.m.Y H:i').($warnings > 0 ? ' · '.__('usage.servers_column_warnings', ['count' => $warnings]) : '');
                    })
                    ->color(fn (Server $record): ?string => $record->accountUsages->contains(fn (AccountUsage $usage): bool => $usage->failure_reason !== null) ? 'danger' : null),
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
                EditAction::make()
                    ->extraModalFooterActions([
                        Action::make('testConnection')
                            ->label(__('servers.test_connection'))
                            ->color('gray')
                            ->action(function (Server $record): void {
                                $result = app(TestServerConnection::class)->handle($record);

                                $notification = Notification::make()->title($result->message);

                                $result->successful ? $notification->success()->send() : $notification->danger()->send();
                            }),
                    ]),
                PullFromServerAction::make(),
            ])
            ->emptyStateHeading(__('servers.empty_heading'))
            ->emptyStateDescription(__('servers.empty_description'))
            ->emptyStateActions([
                CreateAction::make('createEmpty')->label(__('servers.add'))->color('gray'),
            ]);
    }

    /**
     * How many usage warnings each server has right now, by server id.
     *
     * @return array<int, int>
     */
    private static function warningsByServer(): array
    {
        $serverOf = AccountUsage::query()->pluck('server_id', 'id');
        $counts = [];

        foreach (app(ListUsageAlerts::class)->handle() as $alert) {
            $serverId = $serverOf[$alert->accountUsageId] ?? null;

            if ($serverId !== null) {
                $counts[$serverId] = ($counts[$serverId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
