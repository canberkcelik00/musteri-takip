<?php

namespace App\Actions\CustomerServices;

use App\Exceptions\InvalidCustomerService;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Facades\DB;

class RemoveCustomerServiceFreeFlag
{
    public function __construct(
        private AdvanceCustomerServicePeriod $advanceCustomerServicePeriod,
        private CreateRenewalCharge $createRenewalCharge,
        private ReopenCustomerServiceCharge $reopenCustomerServiceCharge,
    ) {}

    /**
     * Make a free customer service paid from its upcoming period on, optionally with a new
     * price. The dates are first brought up to date while the service is still free, so a
     * period that started while it was free never gets a charge. If the renewal window has
     * already started, the upcoming period's charge is created at once and returned;
     * otherwise the renewal run creates it later. If that charge was cancelled when the
     * service was marked free, it is reopened with the current price instead. Returns null when the service was not
     * free, so calling it again never creates a second charge.
     *
     * @throws InvalidCustomerService
     */
    public function handle(CustomerService $customerService, ?int $price = null): ?Charge
    {
        return DB::transaction(function () use ($customerService, $price): ?Charge {
            $customerService = CustomerService::query()
                ->lockForUpdate()
                ->findOrFail($customerService->id);

            if (! $customerService->is_free) {
                return null;
            }

            $price ??= $customerService->price;

            if ($price <= 0) {
                throw InvalidCustomerService::priceNotPositive();
            }

            $this->advanceCustomerServicePeriod->handle($customerService);

            $customerService->refresh()->update([
                'is_free' => false,
                'price' => $price,
            ]);

            return $this->reopenCustomerServiceCharge->handle($customerService, $customerService->end_date->toDateString())
                ?? $this->createRenewalCharge->handle($customerService);
        });
    }
}
