<?php

namespace App\Actions\CustomerServices;

use App\Enums\CustomerServiceStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CancelCustomerService
{
    public function __construct(
        private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod,
        private CancelUpcomingCustomerServiceCharges $cancelUpcomingCustomerServiceCharges,
    ) {}

    /**
     * Cancel the customer service on the given date (today by default) with an optional
     * note; from then on its dates do not advance and it creates no charges. The dates are
     * first brought up to date, so a period that started before the cancellation keeps its
     * charge. Unless told otherwise, the unpaid charge of the upcoming period is cancelled
     * too; a charge of a period that has already started is never cancelled. Returns the
     * cancelled charges; calling it on a cancelled service does nothing.
     *
     * @return Collection<int, Charge>
     */
    public function handle(
        CustomerService $customerService,
        ?string $cancelledOn = null,
        ?string $cancelNote = null,
        bool $cancelUpcomingCharges = true,
    ): Collection {
        return DB::transaction(function () use ($customerService, $cancelledOn, $cancelNote, $cancelUpcomingCharges): Collection {
            $customerService = CustomerService::query()
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if ($customerService->status === CustomerServiceStatus::Cancelled) {
                return new Collection;
            }

            $this->advanceCustomerServicePeriod->handle($customerService);

            $customerService->refresh()->update([
                'status' => CustomerServiceStatus::Cancelled,
                'cancelled_on' => $cancelledOn ?? Carbon::today(config('app.timezone'))->toDateString(),
                'cancel_note' => $cancelNote,
            ]);

            if (! $cancelUpcomingCharges) {
                return new Collection;
            }

            return $this->cancelUpcomingCustomerServiceCharges->handle(
                $customerService,
                __('customer_services.charge_cancel_reason_service_cancelled'),
            );
        });
    }
}
