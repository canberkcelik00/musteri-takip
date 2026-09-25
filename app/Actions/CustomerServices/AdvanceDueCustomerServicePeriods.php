<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdvanceDueCustomerServicePeriods
{
    public function __construct(private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod) {}

    /**
     * Advance every active customer service whose end date has come, up to the current
     * period. Services left behind by missed scheduler runs are included; running it
     * again advances nothing.
     *
     * Each service is processed on its own: a failing service is logged with its id and
     * reported to `$onFailure`, and the remaining services are still processed.
     *
     * @param  (Closure(CustomerService, Throwable): void)|null  $onFailure
     * @return Collection<int, CustomerService> the customer services advanced in this run
     */
    public function handle(?Closure $onFailure = null): Collection
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        /** @var Collection<int, CustomerService> $advanced */
        $advanced = collect();

        CustomerService::query()
            ->where('status', CustomerServiceStatus::Active)
            ->where('end_date', '<=', $today)
            ->chunkById(100, function (Collection $customerServices) use ($advanced, $onFailure): void {
                foreach ($customerServices as $customerService) {
                    try {
                        $wasAdvanced = $this->advanceCustomerServicePeriod->handle($customerService);
                    } catch (Throwable $exception) {
                        Log::error('Customer service period could not be advanced.', [
                            'customer_service_id' => $customerService->id,
                            'customer_id' => $customerService->customer_id,
                            'exception' => $exception,
                        ]);

                        if ($onFailure !== null) {
                            $onFailure($customerService, $exception);
                        }

                        continue;
                    }

                    if ($wasAdvanced) {
                        $advanced->push($customerService->refresh());
                    }
                }
            });

        return $advanced;
    }
}
