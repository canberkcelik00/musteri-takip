<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * @return Collection<int, Charge> the charges created in this run
     */
    public function handle(): Collection
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
            ->chunkById(100, function (Collection $customerServices) use ($charges): void {
                foreach ($customerServices as $customerService) {
                    $charge = $this->createRenewalCharge->handle($customerService);

                    if ($charge !== null) {
                        $charges->push($charge);
                    }
                }
            });

        return $charges;
    }
}
