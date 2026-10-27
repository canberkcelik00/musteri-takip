<?php

namespace App\Livewire;

use App\Actions\Payments\UndoPayment;
use App\Enums\Permission;
use App\Exceptions\PaymentCannotBeUndone;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Windows\RecordPayment;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Listens for the "Geri al" button of the payment notification. It draws nothing; the Action decides whether the
 * payment can still be taken back.
 */
class PaymentUndo extends Component
{
    use AuthorizesPermission;

    public const string UNDO_EVENT = 'undo-payment';

    #[On(self::UNDO_EVENT)]
    public function undo(int $payment): void
    {
        $this->authorizePermission(Permission::MoneyManage);

        $record = Payment::query()->find($payment);

        try {
            if ($record === null) {
                throw PaymentCannotBeUndone::notAvailable();
            }

            $reopened = app(UndoPayment::class)->handle($this->actor(), $record);
        } catch (PaymentCannotBeUndone $exception) {
            $this->dispatch('notify', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch(RecordPayment::PAYMENTS_CHANGED_EVENT);
        $this->dispatch('notify', message: $reopened > 0 ? __('payments.undone_reopened', ['count' => $reopened]) : __('payments.undone'));
    }

    public function render(): View
    {
        return view('livewire.payment-undo');
    }
}
