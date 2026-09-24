<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Models\Charge;

class SyncChargeStatus
{
    /**
     * Mark the charge paid when its allocations cover the amount, open otherwise.
     * Cancelled charges are left untouched.
     */
    public function handle(Charge $charge): Charge
    {
        if ($charge->status === ChargeStatus::Cancelled) {
            return $charge;
        }

        $allocated = (int) $charge->allocations()->sum('amount');

        $charge->status = $allocated >= $charge->amount ? ChargeStatus::Paid : ChargeStatus::Open;
        $charge->save();

        return $charge;
    }
}
