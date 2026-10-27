<?php

namespace App\Actions\Payments;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;

class SumOpenCharges
{
    /**
     * What the customer still owes in one currency, in minor units: the remaining amount of every open charge.
     * Other currencies are never added in.
     */
    public function handle(Customer $customer, Currency $currency): int
    {
        return (int) $customer->charges()
            ->where('currency', $currency)
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->get()
            ->sum(fn (Charge $charge): int => max(0, $charge->remainingAmount()));
    }
}
