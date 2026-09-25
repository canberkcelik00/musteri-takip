<?php

namespace App\Actions\CustomerServices;

use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarkCustomerServiceFree
{
    public function __construct(
        private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod,
        private CancelUpcomingCustomerServiceCharges $cancelUpcomingCustomerServiceCharges,
    ) {}

    /**
     * Mark the customer service free, so it creates no more charges. The dates are first
     * brought up to date while it is still paid, so a period that has already started keeps
     * its charge. Unless told otherwise, the unpaid charge of the upcoming period is
     * cancelled; a charge with allocations is left untouched. Returns the cancelled
     * charges; calling it on a free service does nothing.
     *
     * @return Collection<int, Charge>
     */
    public function handle(CustomerService $customerService, bool $cancelUpcomingCharges = true): Collection
    {
        return DB::transaction(function () use ($customerService, $cancelUpcomingCharges): Collection {
            $customerService = CustomerService::query()
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if ($customerService->is_free) {
                return new Collection;
            }

            $this->advanceCustomerServicePeriod->handle($customerService);

            $customerService->refresh()->update(['is_free' => true]);

            if (! $cancelUpcomingCharges) {
                return new Collection;
            }

            return $this->cancelUpcomingCustomerServiceCharges->handle(
                $customerService,
                __('customer_services.charge_cancel_reason_marked_free'),
            );
        });
    }
}
