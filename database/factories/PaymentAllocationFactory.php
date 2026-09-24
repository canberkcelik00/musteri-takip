<?php

namespace Database\Factories;

use App\Enums\AllocationSource;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default the whole amount of a new charge is allocated from a new payment
 * of the same customer and currency.
 *
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'payment_id' => fn (array $attributes) => $this->paymentFor(Charge::findOrFail($attributes['charge_id']))->id,
            'amount' => fn (array $attributes) => Charge::findOrFail($attributes['charge_id'])->amount,
            'source' => AllocationSource::Payment,
        ];
    }

    /**
     * Indicate that the allocation was applied from the customer's credit balance.
     */
    public function fromCreditBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => AllocationSource::CreditBalance,
        ]);
    }

    private function paymentFor(Charge $charge): Payment
    {
        return Payment::factory()->create([
            'customer_id' => $charge->customer_id,
            'amount' => $charge->amount,
            'currency' => $charge->currency,
        ]);
    }
}
