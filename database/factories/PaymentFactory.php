<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'amount' => fake()->numberBetween(100, 5000) * 100,
            'currency' => Currency::Try,
            'paid_on' => now()->toDateString(),
            'payment_method_id' => PaymentMethod::factory(),
            'note' => null,
        ];
    }
}
