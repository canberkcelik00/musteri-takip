<?php

namespace App\Filament\Actions;

use App\Actions\Servers\BuildUsageReport;
use App\Actions\Servers\RefreshAccountUsage;
use App\Actions\Servers\UsageReport;
use App\Models\CustomerServiceItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

/**
 * The read-only "Kullanım" window of a linked hosting item (docs/UI.md "Kullanım penceresi"):
 * the one window every entry point opens (the card's badge, the edit window, "Bugün"). The item
 * comes in as the action argument `item`. The footer holds "Kapat" and "Şimdi yenile", which
 * reads this one account from the server and leaves the window open with the new values. It only
 * calls BuildUsageReport and RefreshAccountUsage.
 */
class ViewUsageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'viewUsage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('usage.view'))
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => $this->reportOf($arguments)->title ?? __('usage.heading'))
            ->modalDescription(fn (array $arguments): ?string => ($report = $this->reportOf($arguments)) === null
                ? null
                : $report->serverName)
            ->modalWidth(Width::ThreeExtraLarge)
            ->stickyModalFooter()
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('actions.close'))
            ->modalContent(fn (array $arguments) => view('filament.usage.window', ['report' => $this->reportOf($arguments)]))
            ->extraModalFooterActions(fn (Action $action): array => [
                $this->refreshAction((int) ($action->getArguments()['item'] ?? 0)),
            ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function reportOf(array $arguments): ?UsageReport
    {
        $item = CustomerServiceItem::query()->with('server')->find($arguments['item'] ?? 0);

        return $item === null ? null : app(BuildUsageReport::class)->handle($item);
    }

    private function refreshAction(int $itemId): Action
    {
        return Action::make('refreshUsage')
            ->label(__('usage.refresh'))
            ->color('gray')
            ->button()
            ->action(function () use ($itemId): void {
                $report = $this->reportOf(['item' => $itemId]);
                $item = CustomerServiceItem::query()->with('server')->find($itemId);

                if ($report === null || $item?->server === null) {
                    return;
                }

                $usage = app(RefreshAccountUsage::class)->handle($item->server, $report->username);

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
