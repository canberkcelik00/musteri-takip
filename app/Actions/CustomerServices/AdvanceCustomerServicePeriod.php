<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdvanceCustomerServicePeriod
{
    public function __construct(private CreateRenewalCharge $createRenewalCharge) {}

    /**
     * Move the dates of an active customer service (free ones included) forward period by
     * period until its end date is after today, regardless of whether its charges are paid.
     * Before each step the renewal charge of the next period is created if it is missing,
     * so no period's charge is lost when scheduler runs were missed. Cancelled services
     * never advance. Returns whether the dates changed; calling it again changes nothing.
     */
    public function handle(CustomerService $customerService): bool
    {
        return DB::transaction(function () use ($customerService): bool {
            $customerService = CustomerService::query()
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if ($customerService->status !== CustomerServiceStatus::Active) {
                return false;
            }

            $today = Carbon::today(config('app.timezone'))->toDateString();
            $advanced = false;

            while ($customerService->end_date->toDateString() <= $today) {
                $this->createRenewalCharge->handle($customerService);

                $periodStart = $customerService->end_date->toImmutable();

                $customerService->update([
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodStart->addMonthsNoOverflow($customerService->period_months)->toDateString(),
                ]);

                $advanced = true;
            }

            return $advanced;
        });
    }
}
