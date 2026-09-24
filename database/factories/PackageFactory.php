<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\CatalogService;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->word()).' Paket',
            'price' => fake()->numberBetween(1000, 20000) * 100,
            'currency' => Currency::Try,
            'notes' => null,
        ];
    }

    /**
     * Attach the given number of new catalog services to the package.
     */
    public function withCatalogServices(int $count = 3): static
    {
        return $this->hasAttached(CatalogService::factory()->count($count));
    }

    /**
     * Indicate that the package is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
