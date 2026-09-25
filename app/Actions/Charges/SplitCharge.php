<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SplitCharge
{
    public function __construct(private CreateCharge $createCharge) {}

    /**
     * Split the given amount off an open charge without allocations into a new charge
     * with its own due date, and return the new charge. The total stays the same. The
     * original charge keeps its period, due date and description; the new charge belongs
     * to the same customer service but has no period, since a period is charged once.
     *
     * @throws InvalidCharge
     */
    public function handle(Charge $charge, int $amount, CarbonInterface|string $dueDate, ?string $description = null): Charge
    {
        return DB::transaction(function () use ($charge, $amount, $dueDate, $description): Charge {
            $charge->customer->charges()
                ->where('currency', $charge->currency)
                ->lockForUpdate()
                ->pluck('id');

            $charge = Charge::query()->withSum('allocations', 'amount')->findOrFail($charge->id);

            if ($charge->status !== ChargeStatus::Open) {
                throw InvalidCharge::notOpen();
            }

            if ($charge->allocatedAmount() > 0) {
                throw InvalidCharge::hasAllocations();
            }

            if ($amount <= 0 || $amount >= $charge->amount) {
                throw InvalidCharge::invalidSplitAmount();
            }

            $charge->update(['amount' => $charge->amount - $amount]);

            $description = trim((string) $description);

            return $this->createCharge->handle($charge->customer, [
                'customer_service_id' => $charge->customer_service_id,
                'description' => $description !== '' ? $description : $charge->description,
                'amount' => $amount,
                'currency' => $charge->currency,
                'due_date' => $dueDate,
            ]);
        });
    }
}
