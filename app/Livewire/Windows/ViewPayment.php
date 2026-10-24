<?php

namespace App\Livewire\Windows;

use App\Enums\AllocationSource;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A payment's detail window (docs/UI.md §2 Kural 7, 8, 10): date, customer, amount, method, note and, when part of
 * the payment went to the credit balance, a line that says so; the allocation rows sit in a collapsed section titled
 * with their count, and "Sil" is the last thing in the window, apart, red and confirmed. Clicking an amount never
 * deletes anything by itself.
 */
class ViewPayment extends Component
{
    #[Locked]
    public int $paymentId = 0;

    public function mount(int $payment): void
    {
        $this->paymentId = Payment::query()->findOrFail($payment)->id;
    }

    public function render(): View
    {
        $payment = Payment::query()
            ->with(['customer', 'paymentMethod', 'allocations.charge'])
            ->findOrFail($this->paymentId);

        $allocated = (int) $payment->allocations->where('source', AllocationSource::Payment)->sum('amount');
        $credit = $payment->amount - $allocated;

        return view('livewire.windows.view-payment', [
            'payment' => $payment,
            'creditText' => $credit > 0
                ? __('payments.result_credit', ['amount' => (new Money($credit, $payment->currency))->format()])
                : null,
        ]);
    }
}
