<?php

namespace App\Actions\Customers;

use App\Enums\CustomerServiceStatus;
use App\Exceptions\CustomerHasActiveServices;
use App\Models\Customer;

class ArchiveCustomer
{
    /**
     * Archive a customer whose services are all cancelled; an active service would
     * otherwise keep renewing while the customer is hidden from lists and the digest.
     *
     * @throws CustomerHasActiveServices
     */
    public function handle(Customer $customer): void
    {
        if ($customer->customerServices()->where('status', CustomerServiceStatus::Active)->exists()) {
            throw new CustomerHasActiveServices;
        }

        $customer->archive();
    }
}
