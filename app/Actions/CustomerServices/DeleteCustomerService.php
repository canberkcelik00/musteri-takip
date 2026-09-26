<?php

namespace App\Actions\CustomerServices;

use App\Exceptions\RecordInUse;
use App\Models\CustomerService;
use Illuminate\Support\Facades\DB;

class DeleteCustomerService
{
    /**
     * Delete a customer service that has no charge, cancelled ones included (its items go with
     * it); a service with a charge is cancelled instead. The row is locked so a concurrent charge
     * cannot slip in between the check and the delete.
     *
     * @throws RecordInUse
     */
    public function handle(CustomerService $customerService): void
    {
        DB::transaction(function () use ($customerService): void {
            $locked = CustomerService::query()->lockForUpdate()->find($customerService->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('customer_service');
            }

            $locked->delete();
        });
    }
}
