<?php

namespace App\Actions\Payments;

use App\Actions\Charges\SyncChargeStatus;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DeletePayment
{
    public function __construct(private SyncChargeStatus $syncChargeStatus) {}

    /**
     * Delete a payment with its allocations; the charges it paid are open again.
     */
    public function handle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $chargeIds = $payment->allocations()->pluck('charge_id')->all();

            $payment->delete();

            foreach (Charge::query()->whereKey($chargeIds)->lockForUpdate()->get() as $charge) {
                $this->syncChargeStatus->handle($charge);
            }
        });
    }
}
