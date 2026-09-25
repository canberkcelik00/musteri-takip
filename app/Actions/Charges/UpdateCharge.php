<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UpdateCharge
{
    public function __construct(private ApplyCreditBalance $applyCreditBalance) {}

    /**
     * Change the amount and/or due date of an open charge. The amount of a charge with
     * any allocation (from a payment or the credit balance) cannot change; its due date
     * can. The customer's credit balance is applied afterwards, so an open charge and a
     * credit balance never exist side by side in the same currency.
     *
     * @param  array{amount?: int, due_date?: CarbonInterface|string}  $attributes
     *
     * @throws InvalidCharge
     */
    public function handle(Charge $charge, array $attributes): Charge
    {
        return DB::transaction(function () use ($charge, $attributes): Charge {
            $charge->customer->charges()
                ->where('currency', $charge->currency)
                ->lockForUpdate()
                ->pluck('id');

            $charge = Charge::query()->withSum('allocations', 'amount')->findOrFail($charge->id);

            if ($charge->status !== ChargeStatus::Open) {
                throw InvalidCharge::notOpen();
            }

            if (array_key_exists('amount', $attributes) && $attributes['amount'] !== $charge->amount) {
                if ($charge->allocatedAmount() > 0) {
                    throw InvalidCharge::hasAllocations();
                }

                if ($attributes['amount'] <= 0) {
                    throw InvalidCharge::amountNotPositive();
                }

                $charge->amount = $attributes['amount'];
            }

            if (array_key_exists('due_date', $attributes)) {
                $charge->fill(['due_date' => $attributes['due_date']]);
            }

            $charge->save();

            return $this->applyCreditBalance->handle($charge);
        });
    }
}
