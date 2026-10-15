<?php

namespace Database\Factories;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default a manual charge (no customer service or period), open and due today.
 *
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
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
            'customer_service_id' => null,
            'description' => 'Ek hizmet: '.fake()->words(2, true),
            'amount' => fake()->numberBetween(100, 5000) * 100,
            'currency' => Currency::Try,
            'due_date' => now()->toDateString(),
            'period_start' => null,
            'period_end' => null,
            'status' => ChargeStatus::Open,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    /**
     * Indicate that the charge is for the current period of the given customer service,
     * due on the period start.
     */
    public function forCustomerService(CustomerService $customerService): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customerService->customer_id,
            'customer_service_id' => $customerService->id,
            'description' => $customerService->name.' '.$customerService->start_date->format('Y').'–'.$customerService->end_date->format('Y'),
            'amount' => $customerService->price,
            'currency' => $customerService->currency,
            'due_date' => $customerService->start_date->toDateString(),
            'period_start' => $customerService->start_date->toDateString(),
            'period_end' => $customerService->end_date->toDateString(),
        ]);
    }

    /**
     * Indicate that the charge is a renewal charge (G-3.4).
     */
    public function renewal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_renewal' => true,
        ]);
    }

    /**
     * Indicate that the renewal reminder was already sent.
     */
    public function reminderSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the charge is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Paid,
        ]);
    }

    /**
     * Indicate that the charge is cancelled with a reason.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Müşteri hizmeti iptal etti.',
        ]);
    }
}
