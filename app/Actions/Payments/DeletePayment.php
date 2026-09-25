<?php

namespace App\Actions\Payments;

use App\Actions\Charges\ApplyCreditBalance;
use App\Actions\Charges\SyncChargeStatus;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DeletePayment
{
    public function __construct(
        private SyncChargeStatus $syncChargeStatus,
        private ApplyCreditBalance $applyCreditBalance,
    ) {}

    /**
     * Delete a payment with its allocations; the charges it paid are open again.
     * The customer's remaining credit balance is then applied to the reopened charges,
     * oldest due date first, so an open charge and a credit balance never exist side by
     * side in the same currency.
     */
    public function handle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $chargeIds = $payment->allocations()->pluck('charge_id')->all();

            $payment->delete();

            $charges = Charge::query()
                ->whereKey($chargeIds)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($charges as $charge) {
                $this->syncChargeStatus->handle($charge);
            }

            foreach ($charges as $charge) {
                $this->applyCreditBalance->handle($charge);
            }
        });
    }
}
