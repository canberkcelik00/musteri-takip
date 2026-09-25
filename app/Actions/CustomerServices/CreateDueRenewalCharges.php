<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Settings;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateDueRenewalCharges
{
    public function __construct(
        private CreateRenewalCharge $createRenewalCharge,
        private Settings $settings,
    ) {}

    /**
     * Create the renewal charge of every active, non-free customer service whose renewal
     * window has started and whose next period has no charge yet. Services whose window
     * passed without a charge (e.g. missed scheduler runs) are included, so a later run
     * completes them. Running it again creates nothing new.
     *
     * Each service is processed on its own: a failing service is logged with its id and
     * reported to `$onFailure`, and the remaining services are still processed.
     *
     * @param  (Closure(CustomerService, Throwable): void)|null  $onFailure
     * @return Collection<int, Charge> the charges created in this run
     */
    public function handle(?Closure $onFailure = null): Collection
    {
        $lastDueEndDate = Carbon::today(config('app.timezone'))
            ->addDays($this->settings->renewalLeadDays())
            ->toDateString();

        /** @var Collection<int, Charge> $charges */
        $charges = collect();

        CustomerService::query()
            ->where('status', CustomerServiceStatus::Active)
            ->where('is_free', false)
            ->where('end_date', '<=', $lastDueEndDate)
            ->whereHas('customer', fn ($query) => $query->notArchived())
            ->whereDoesntHave('charges', fn ($query) => $query->whereColumn('charges.period_start', 'customer_services.end_date'))
            ->chunkById(100, function (Collection $customerServices) use ($charges, $onFailure): void {
                foreach ($customerServices as $customerService) {
                    try {
                        $charge = $this->createRenewalCharge->handle($customerService);
                    } catch (Throwable $exception) {
                        Log::error('Renewal charge could not be created.', [
                            'customer_service_id' => $customerService->id,
                            'customer_id' => $customerService->customer_id,
                            'exception' => $exception,
                        ]);

                        if ($onFailure !== null) {
                            $onFailure($customerService, $exception);
                        }

                        continue;
                    }

                    if ($charge !== null) {
                        $charges->push($charge);
                    }
                }
            });

        return $charges;
    }
}
