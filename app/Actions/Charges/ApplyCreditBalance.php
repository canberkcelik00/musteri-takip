<?php

namespace App\Actions\Charges;

use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ApplyCreditBalance
{
    public function __construct(private SyncChargeStatus $syncChargeStatus) {}

    /**
     * Apply the customer's credit balance in the charge's currency to the charge,
     * using the oldest payments first. Allocations are saved with the credit balance
     * source, apart from those made when the payment was recorded. Does nothing for a
     * charge that is not open or has nothing left to pay, so calling it again is safe.
     */
    public function handle(Charge $charge): Charge
    {
        return DB::transaction(function () use ($charge): Charge {
            $charge->customer->charges()
                ->where('currency', $charge->currency)
                ->lockForUpdate()
                ->pluck('id');

            $charge = Charge::query()->withSum('allocations', 'amount')->findOrFail($charge->id);

            if ($charge->status !== ChargeStatus::Open) {
                return $charge;
            }

            $remaining = $charge->remainingAmount();

            if ($remaining <= 0) {
                return $charge;
            }

            $payments = $charge->customer->payments()
                ->where('currency', $charge->currency)
                ->withSum('allocations', 'amount')
                ->orderBy('paid_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                if ($remaining === 0) {
                    break;
                }

                $unallocated = $payment->amount - (int) $payment->getAttribute('allocations_sum_amount');

                if ($unallocated <= 0) {
                    continue;
                }

                $share = min($unallocated, $remaining);
                $this->allocate($payment, $charge, $share);
                $remaining -= $share;
            }

            return $this->syncChargeStatus->handle(Charge::query()->findOrFail($charge->id));
        });
    }

    private function allocate(Payment $payment, Charge $charge, int $amount): void
    {
        $existing = $payment->allocations()
            ->where('charge_id', $charge->id)
            ->where('source', AllocationSource::CreditBalance)
            ->first();

        if ($existing !== null) {
            $existing->increment('amount', $amount);

            return;
        }

        $payment->allocations()->create([
            'charge_id' => $charge->id,
            'amount' => $amount,
            'source' => AllocationSource::CreditBalance,
        ]);
    }
}
