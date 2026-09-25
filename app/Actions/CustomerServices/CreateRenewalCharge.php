<?php

namespace App\Actions\CustomerServices;

use App\Actions\Charges\CreateCharge;
use App\Enums\CustomerServiceStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateRenewalCharge
{
    public function __construct(
        private CreateCharge $createCharge,
        private Settings $settings,
    ) {}

    /**
     * Create the next period's charge of the customer service once its end date is within
     * the renewal lead days: due on the end date, for the period starting on it. Returns
     * null when the service is cancelled or free, its customer is archived, the renewal
     * window has not started yet, or a charge (even a cancelled one) already exists for
     * that period, so calling it again never creates a second charge.
     */
    public function handle(CustomerService $customerService): ?Charge
    {
        return DB::transaction(function () use ($customerService): ?Charge {
            $customerService = CustomerService::query()
                ->with('customer')
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if (! $this->isDue($customerService)) {
                return null;
            }

            $periodStart = $customerService->end_date->toImmutable();
            $periodEnd = $periodStart->addMonthsNoOverflow($customerService->period_months);

            return $this->createCharge->handle($customerService->customer, [
                'customer_service_id' => $customerService->id,
                'description' => $customerService->name.' '.$periodStart->format('Y').'–'.$periodEnd->format('Y'),
                'amount' => $customerService->price,
                'currency' => $customerService->currency,
                'due_date' => $periodStart->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ]);
        });
    }

    private function isDue(CustomerService $customerService): bool
    {
        if ($customerService->status !== CustomerServiceStatus::Active || $customerService->is_free) {
            return false;
        }

        if ($customerService->customer->isArchived()) {
            return false;
        }

        $windowStart = $customerService->end_date->toImmutable()->subDays($this->settings->renewalLeadDays());

        if ($windowStart->toDateString() > Carbon::today(config('app.timezone'))->toDateString()) {
            return false;
        }

        return $customerService->charges()
            ->whereDate('period_start', $customerService->end_date->toDateString())
            ->doesntExist();
    }
}
