<?php

namespace App\Filament\Resources\Servers\Tables;

use App\Actions\Servers\BuildResellerSummary;
use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\TestServerConnection;
use App\Enums\UsageLevel;
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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ServersTable
{
    public static function configure(Table $table): Table
    {
        $warningsByServer = null;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->scopes(['withInUse'])->with(['accountUsages', 'serverUsage']))
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
                        $failed = $record->accountUsages->first(fn (AccountUsage $usage): bool => $usage->failure_reason !== null) ?? ($record->serverUsage?->failure_reason !== null ? $record->serverUsage : null);

                        if ($failed !== null) {
                            return __('usage.servers_column_failed', ['reason' => __('usage.failure.'.$failed->failure_reason->value)]);
                        }

                        $refreshedAt = collect([$record->accountUsages->max('refreshed_at'), $record->serverUsage?->refreshed_at])->filter()->max();

                        if ($refreshedAt === null) {
                            return __('usage.never_read');
                        }

                        $warningsByServer ??= self::warningsByServer();
                        $warnings = $warningsByServer[$record->id] ?? 0;

                        return $refreshedAt->format('d.m.Y H:i').($warnings > 0 ? ' · '.__('usage.servers_column_warnings', ['count' => $warnings]) : '');
                    })
                    ->description(function (Server $record): string|Htmlable|null {
                        $summary = self::resellerSummary($record);

                        if ($summary === null || $summary['level'] === null) {
                            return $summary['text'] ?? null;
                        }

                        return new HtmlString('<span class="fi-usage-value fi-usage-level-'.$summary['level']->value.'">'.e($summary['text']).'</span>');
                    })
                    ->color(fn (Server $record): ?string => $record->accountUsages->contains(fn (AccountUsage $usage): bool => $usage->failure_reason !== null) || $record->serverUsage?->failure_reason !== null ? 'danger' : null),
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
     * The reseller line under the "Kullanım" column of a WHM server, once its totals were read.
     *
     * @return array{text: string, level: UsageLevel|null}|null
     */
    private static function resellerSummary(Server $record): ?array
    {
        return $record->serverUsage === null ? null : app(BuildResellerSummary::class)->handle($record->serverUsage);
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
            $serverId = $alert->serverId ?? $serverOf[$alert->accountUsageId] ?? null;

            if ($serverId !== null) {
                $counts[$serverId] = ($counts[$serverId] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
