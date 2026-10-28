<?php

namespace Database\Factories;

use App\Models\CustomerServiceItem;
use App\Models\SiteIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Open by default (the site is still down).
 *
 * @extends Factory<SiteIncident>
 */
class SiteIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_service_item_id' => CustomerServiceItem::factory(),
            'domain' => fake()->domainName(),
            'started_at' => now()->subHour(),
            'ended_at' => null,
            'reason' => 'HTTP 503',
        ];
    }

    /**
     * Indicate that the site answered again.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['ended_at' => now()]);
    }
}
