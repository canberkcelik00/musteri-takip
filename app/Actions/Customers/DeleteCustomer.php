<?php

namespace App\Actions\Customers;

use App\Exceptions\RecordInUse;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomer
{
    /**
     * Delete a customer with no service, charge or payment (the contacts go with it); a customer
     * in use is archived instead. The row is locked so a concurrent insert that references it
     * cannot slip in between the check and the delete.
     *
     * @throws RecordInUse
     */
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $locked = Customer::query()->lockForUpdate()->find($customer->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->isInUse()) {
                throw new RecordInUse('customer');
            }

            $locked->delete();
        });
    }
}
