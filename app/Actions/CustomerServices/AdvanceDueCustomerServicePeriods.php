<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdvanceDueCustomerServicePeriods
{
    public function __construct(private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod) {}

    /**
     * Advance every active customer service whose end date has come, up to the current
     * period. Services left behind by missed scheduler runs are included; running it
     * again advances nothing.
     *
     * @return Collection<int, CustomerService> the customer services advanced in this run
     */
    public function handle(): Collection
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        /** @var Collection<int, CustomerService> $advanced */
        $advanced = collect();

        CustomerService::query()
            ->where('status', CustomerServiceStatus::Active)
            ->where('end_date', '<=', $today)
            ->chunkById(100, function (Collection $customerServices) use ($advanced): void {
                foreach ($customerServices as $customerService) {
                    if ($this->advanceCustomerServicePeriod->handle($customerService)) {
                        $advanced->push($customerService->refresh());
                    }
                }
            });

        return $advanced;
    }
}
