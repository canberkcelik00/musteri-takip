<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogService>
 */
class CatalogServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(ServiceType::cases());

        return [
            'type' => $type,
            'name' => $type->getLabel().' '.fake()->words(2, true),
            'price' => fake()->numberBetween(50, 5000) * 100,
            'currency' => Currency::Try,
            'details' => $this->detailsFor($type),
            'notes' => null,
        ];
    }

    /**
     * Indicate that the catalog service is of the given type, with details for that type.
     */
    public function ofType(ServiceType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'details' => $this->detailsFor($type),
        ]);
    }

    /**
     * Indicate that the catalog service is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function detailsFor(ServiceType $type): array
    {
        return collect($type->catalogDetailKeys())
            ->mapWithKeys(fn (string $key) => [$key => fake()->word()])
            ->all();
    }
}
