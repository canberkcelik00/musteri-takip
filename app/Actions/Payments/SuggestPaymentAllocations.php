<?php

namespace App\Actions\Payments;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Exceptions\InvalidPaymentAllocation;
use App\Models\Charge;
use App\Models\Customer;

class SuggestPaymentAllocations
{
    /**
     * Work out how a payment is allocated to the customer's charges, without saving.
     *
     * Manual allocations are applied first. The rest goes to the customer's other open
     * charges in the same currency, oldest due date first, then to what is left on the
     * manually allocated charges; only an amount no open charge can take is left as
     * credit balance. So an open charge and a credit balance never exist side by side
     * in the same currency.
     *
     * @param  array<int, int>  $manualAllocations  Amounts in minor units keyed by charge id.
     * @return array<int, int> Amounts in minor units keyed by charge id.
     *
     * @throws InvalidPaymentAllocation
     */
    public function handle(Customer $customer, Currency $currency, int $amount, array $manualAllocations = []): array
    {
        if ($amount <= 0) {
            throw InvalidPaymentAllocation::amountNotPositive();
        }

        $allocations = $this->validatedManualAllocations($customer, $currency, $amount, $manualAllocations);
        $unallocated = $amount - array_sum($allocations);

        $openCharges = $customer->charges()
            ->where('currency', $currency)
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        [$manuallyAllocated, $others] = $openCharges->partition(
            fn (Charge $charge): bool => array_key_exists($charge->id, $allocations),
        );

        foreach ($others->concat($manuallyAllocated) as $charge) {
            if ($unallocated === 0) {
                break;
            }

            $capacity = $charge->remainingAmount() - ($allocations[$charge->id] ?? 0);

            if ($capacity <= 0) {
                continue;
            }

            $share = min($capacity, $unallocated);
            $allocations[$charge->id] = ($allocations[$charge->id] ?? 0) + $share;
            $unallocated -= $share;
        }

        return $allocations;
    }

    /**
     * @param  array<int, int>  $manualAllocations
     * @return array<int, int>
     *
     * @throws InvalidPaymentAllocation
     */
    private function validatedManualAllocations(Customer $customer, Currency $currency, int $amount, array $manualAllocations): array
    {
        if ($manualAllocations === []) {
            return [];
        }

        $charges = Charge::query()
            ->withSum('allocations', 'amount')
            ->findOrFail(array_keys($manualAllocations))
            ->keyBy('id');

        $total = 0;

        foreach ($manualAllocations as $chargeId => $allocationAmount) {
            $charge = $charges[$chargeId];

            if ($allocationAmount <= 0) {
                throw InvalidPaymentAllocation::amountNotPositive();
            }

            if (! $charge->customer()->is($customer)) {
                throw InvalidPaymentAllocation::chargeOfAnotherCustomer();
            }

            if ($charge->currency !== $currency) {
                throw InvalidPaymentAllocation::currencyMismatch();
            }

            if ($charge->status !== ChargeStatus::Open) {
                throw InvalidPaymentAllocation::chargeNotOpen();
            }

            if ($allocationAmount > $charge->remainingAmount()) {
                throw InvalidPaymentAllocation::exceedsChargeRemaining();
            }

            $total += $allocationAmount;

            if ($total > $amount) {
                throw InvalidPaymentAllocation::exceedsPaymentAmount();
            }
        }

        return $manualAllocations;
    }
}
