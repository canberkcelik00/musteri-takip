<?php

namespace App\Actions\Payments;

use App\Actions\Charges\ApplyCreditBalance;
use App\Actions\Charges\SyncChargeStatus;
use App\Enums\ChargeStatus;
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
     *
     * Returns how many paid charges are open again afterwards, for the notification.
     */
    public function handle(Payment $payment): int
    {
        return DB::transaction(function () use ($payment): int {
            $chargeIds = $payment->allocations()->pluck('charge_id')->all();
            $paidBefore = Charge::query()->whereKey($chargeIds)->where('status', ChargeStatus::Paid)->pluck('id')->all();

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

            return Charge::query()->whereKey($paidBefore)->where('status', ChargeStatus::Open)->count();
        });
    }
}
