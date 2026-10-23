<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default a draft quote for an existing customer, valid for 15 days, with no items.
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'TKL-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'status' => QuoteStatus::Draft,
            'valid_until' => now()->addDays(15)->toDateString(),
            'notes' => null,
        ];
    }

    /**
     * Indicate that the recipient is a person written by hand instead of an existing customer.
     */
    public function manualRecipient(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => null,
            'recipient_name' => fake()->name(),
            'recipient_company' => fake()->company(),
            'recipient_phone' => fake()->numerify('0111 111 11 ##'),
            'recipient_email' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the quote was e-mailed and is still valid.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
            'sent_to' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the quote was sent and its validity ended yesterday.
     */
    public function expired(): static
    {
        return $this->sent()->state(fn (array $attributes) => [
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }

    /**
     * Indicate that the quote was accepted.
     */
    public function accepted(): static
    {
        return $this->sent()->state(fn (array $attributes) => [
            'status' => QuoteStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the quote was rejected.
     */
    public function rejected(): static
    {
        return $this->sent()->state(fn (array $attributes) => [
            'status' => QuoteStatus::Rejected,
            'rejected_at' => now(),
        ]);
    }
}
