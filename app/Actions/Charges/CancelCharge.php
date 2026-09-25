<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;

class CancelCharge
{
    /**
     * Cancel the charge with a reason. A charge with any allocation (from a payment or
     * the credit balance) cannot be cancelled; the payment must be deleted first. An
     * already cancelled charge is left untouched.
     *
     * @throws InvalidCharge
     */
    public function handle(Charge $charge, string $reason): Charge
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw InvalidCharge::cancelReasonRequired();
        }

        return DB::transaction(function () use ($charge, $reason): Charge {
            $charge->customer->charges()
                ->where('currency', $charge->currency)
                ->lockForUpdate()
                ->pluck('id');

            $charge = Charge::query()->findOrFail($charge->id);

            if ($charge->status === ChargeStatus::Cancelled) {
                return $charge;
            }

            if ($charge->allocations()->exists()) {
                throw InvalidCharge::cannotCancelWithAllocations();
            }

            $charge->update([
                'status' => ChargeStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $charge;
        });
    }
}
