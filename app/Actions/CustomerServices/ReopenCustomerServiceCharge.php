<?php

namespace App\Actions\CustomerServices;

use App\Actions\Charges\ApplyCreditBalance;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Facades\DB;

class ReopenCustomerServiceCharge
{
    public function __construct(private ApplyCreditBalance $applyCreditBalance) {}

    /**
     * Reopen the cancelled charge of the customer service for the period starting on the
     * given date, if it has no allocations. Amount, currency and description are taken
     * from the service again, since its price may have changed while the charge was
     * cancelled, and the customer's credit balance is applied. Returns null when there is
     * no such charge.
     */
    public function handle(CustomerService $customerService, string $periodStart): ?Charge
    {
        return DB::transaction(function () use ($customerService, $periodStart): ?Charge {
            $charge = $customerService->charges()
                ->where('status', ChargeStatus::Cancelled)
                ->whereDate('period_start', $periodStart)
                ->whereDoesntHave('allocations')
                ->lockForUpdate()
                ->first();

            if ($charge === null) {
                return null;
            }

            $charge->update([
                'description' => $customerService->name.' '.$charge->period_start?->format('Y').'–'.$charge->period_end?->format('Y'),
                'amount' => $customerService->price,
                'currency' => $customerService->currency,
                'status' => ChargeStatus::Open,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);

            return $this->applyCreditBalance->handle($charge);
        });
    }
}
