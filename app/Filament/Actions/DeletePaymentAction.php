<?php

namespace App\Filament\Actions;

use App\Actions\Payments\DeletePayment;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Deletes a wrongly entered payment through DeletePayment after confirmation; the charges it
 * paid are open again.
 */
class DeletePaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deletePayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('payments.delete'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('payments.delete_heading'))
            ->modalDescription(__('payments.delete_description'))
            ->successNotificationTitle(__('payments.deleted'))
            ->action(function (Payment $record, DeletePayment $deletePayment, Component $livewire): void {
                $reopened = $deletePayment->handle($record);

                $this->successNotificationTitle($reopened > 0
                    ? __('payments.deleted_reopened', ['count' => $reopened])
                    : __('payments.deleted'));
                $this->success();

                $livewire->dispatch(RecordPaymentAction::PAYMENTS_CHANGED_EVENT);
            });
    }
}
