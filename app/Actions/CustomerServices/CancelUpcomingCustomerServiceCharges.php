<?php

namespace App\Actions\CustomerServices;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CancelUpcomingCustomerServiceCharges
{
    /**
     * Cancel the unpaid charges of the customer service for periods that have not started
     * yet (period start after today) with the given reason, and return them. A charge of a
     * period that has already started is never cancelled, nor is a charge with any
     * allocation (from a payment or the credit balance).
     *
     * @return Collection<int, Charge>
     */
    public function handle(CustomerService $customerService, string $reason): Collection
    {
        return DB::transaction(function () use ($customerService, $reason): Collection {
            $charges = $customerService->charges()
                ->where('status', ChargeStatus::Open)
                ->whereDate('period_start', '>', Carbon::today(config('app.timezone'))->toDateString())
                ->whereDoesntHave('allocations')
                ->lockForUpdate()
                ->get();

            $charges->each(fn (Charge $charge) => $charge->update([
                'status' => ChargeStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]));

            return $charges;
        });
    }
}
