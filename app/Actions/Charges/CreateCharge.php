<?php

namespace App\Actions\Charges;

use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreateCharge
{
    public function __construct(private ApplyCreditBalance $applyCreditBalance) {}

    /**
     * Create the customer's charge and apply their credit balance in the same currency
     * to it. Every new charge is created through this action. A charge of a customer
     * service must belong to the customer of that service.
     *
     * @param  array{description: string, amount: int, currency: Currency, due_date: CarbonInterface|string, customer_service_id?: ?int, period_start?: CarbonInterface|string|null, period_end?: CarbonInterface|string|null}  $attributes
     */
    public function handle(Customer $customer, array $attributes): Charge
    {
        $customerServiceId = $attributes['customer_service_id'] ?? null;

        if ($customerServiceId !== null && (int) CustomerService::query()->whereKey($customerServiceId)->value('customer_id') !== $customer->id) {
            throw InvalidCharge::customerServiceOfAnotherCustomer();
        }

        return DB::transaction(function () use ($customer, $attributes): Charge {
            $charge = $customer->charges()->create($attributes);

            return $this->applyCreditBalance->handle($charge);
        });
    }
}
