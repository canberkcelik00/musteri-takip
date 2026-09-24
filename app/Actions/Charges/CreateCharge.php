<?php

namespace App\Actions\Charges;

use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreateCharge
{
    public function __construct(private ApplyCreditBalance $applyCreditBalance) {}

    /**
     * Create the customer's charge and apply their credit balance in the same currency
     * to it. Every new charge is created through this action.
     *
     * @param  array{description: string, amount: int, currency: Currency, due_date: CarbonInterface|string, customer_service_id?: ?int, period_start?: CarbonInterface|string|null, period_end?: CarbonInterface|string|null}  $attributes
     */
    public function handle(Customer $customer, array $attributes): Charge
    {
        return DB::transaction(function () use ($customer, $attributes): Charge {
            $charge = $customer->charges()->create($attributes);

            return $this->applyCreditBalance->handle($charge);
        });
    }
}
