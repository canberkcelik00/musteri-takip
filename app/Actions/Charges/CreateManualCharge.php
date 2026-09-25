<?php

namespace App\Actions\Charges;

use App\Enums\Currency;
use App\Exceptions\InvalidCharge;
use App\Models\Charge;
use App\Models\Customer;
use Carbon\CarbonInterface;

class CreateManualCharge
{
    public function __construct(private CreateCharge $createCharge) {}

    /**
     * Create a charge that no rule produces, optionally for one of the customer's
     * services. It never has a period. The customer's credit balance is applied.
     *
     * @param  array{description: string, amount: int, currency: Currency, due_date: CarbonInterface|string, customer_service_id?: ?int}  $attributes
     *
     * @throws InvalidCharge
     */
    public function handle(Customer $customer, array $attributes): Charge
    {
        $description = trim($attributes['description']);

        if ($description === '') {
            throw InvalidCharge::descriptionRequired();
        }

        if ($attributes['amount'] <= 0) {
            throw InvalidCharge::amountNotPositive();
        }

        return $this->createCharge->handle($customer, [
            'customer_service_id' => $attributes['customer_service_id'] ?? null,
            'description' => $description,
            'amount' => $attributes['amount'],
            'currency' => $attributes['currency'],
            'due_date' => $attributes['due_date'],
        ]);
    }
}
