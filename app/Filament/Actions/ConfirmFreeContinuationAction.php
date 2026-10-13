<?php

namespace App\Filament\Actions;

use App\Actions\CustomerServices\ConfirmFreeContinuation;
use App\Filament\Concerns\ResolvesCustomerServiceArgument;
use App\Models\CustomerService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * "Ücretsiz devam et" on the "Bugün" page's free-decision rows (G-4.15, docs/UI.md §5): records
 * that the free service continues, so it stops appearing as a pending decision. Record reaches
 * this action through a customerService argument (RemoveFreeFlagAction's technique), since these
 * rows are not table rows.
 */
class ConfirmFreeContinuationAction extends Action
{
    use ResolvesCustomerServiceArgument;

    public static function getDefaultName(): ?string
    {
        return 'confirmFreeContinuation';
    }

    public function getRecord(bool $withDefault = true): CustomerService|array|null
    {
        return $this->customerServiceFromArguments();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('customer_services.confirm_free_continuation'))
            ->icon(Heroicon::Check)
            ->requiresConfirmation()
            ->visible(fn (?CustomerService $record): bool => $record !== null)
            ->action(function (CustomerService $record, Action $action, ConfirmFreeContinuation $confirmFreeContinuation): void {
                if (! $confirmFreeContinuation->handle($record)) {
                    Notification::make()
                        ->warning()
                        ->title(__('customer_services.free_action_not_applied'))
                        ->send();

                    return;
                }

                $action->successNotificationTitle(__('customer_services.free_continuation_confirmed'))->success();
            });
    }
}
