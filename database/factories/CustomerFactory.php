<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shortName = fake()->unique()->company();

        return [
            'short_name' => $shortName,
            'legal_title' => $shortName.' Ltd. Şti.',
            'tax_office' => fake()->city(),
            'tax_number' => fake()->numerify('##########'),
            'billing_address' => fake()->address(),
            'notes' => null,
        ];
    }

    /**
     * Indicate that the customer is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
