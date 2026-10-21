<?php

namespace App\Filament\Actions;

use App\Actions\Servers\BuildServerUsageReport;
use App\Actions\Servers\RefreshServerTotals;
use App\Actions\Servers\ServerUsageReport;
use App\Enums\ServerType;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * The read-only "Kullanım" window of a WHM server (docs/UI.md "Sunucu kullanım penceresi"):
 * the reseller totals and every account of the server. Opened by a click on the "Kullanım"
 * column of the servers list, so the row actions stay at two. The footer holds "Kapat" and
 * "Şimdi yenile", which reads the server's totals and account list again and leaves the window
 * open with the new values. It only calls BuildServerUsageReport and RefreshServerTotals.
 */
class ViewServerUsageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'viewServerUsage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('usage.server_view'))
            ->visible(fn (Server $record): bool => $record->type === ServerType::Whm)
            ->color('gray')
            ->modalHeading(fn (Server $record): string => $record->name)
            ->modalDescription(fn (Server $record): string => $record->hostname)
            ->modalWidth(Width::FiveExtraLarge)
            ->stickyModalFooter()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('actions.close'))
            ->modalContent(fn (Server $record) => view('filament.usage.server-window', ['report' => $this->reportOf($record)]))
            ->extraModalFooterActions(fn (Server $record): array => [
                $this->refreshAction($record),
            ]);
    }

    private function reportOf(Server $server): ?ServerUsageReport
    {
        return app(BuildServerUsageReport::class)->handle($server);
    }

    private function refreshAction(Server $server): Action
    {
        return Action::make('refreshServerUsage')
            ->label(__('usage.refresh'))
            ->color('gray')
            ->button()
            ->action(function () use ($server): void {
                $usage = app(RefreshServerTotals::class)->handle($server);

                if ($usage->failure_reason === null) {
                    Notification::make()->title(__('usage.refreshed'))->success()->send();

                    return;
                }

                Notification::make()
                    ->title(__('usage.refresh_failed', ['reason' => __('usage.failure.'.$usage->failure_reason->value)]))
                    ->body(__('servers.connection_'.$usage->failure_reason->value))
                    ->danger()
                    ->send();
            });
    }
}
