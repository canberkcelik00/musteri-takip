<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Without `for()`, a new customer service (with its own items) is created as parent.
 *
 * @extends Factory<CustomerServiceItem>
 */
class CustomerServiceItemFactory extends Factory
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
            'customer_service_id' => CustomerService::factory(),
            'catalog_service_id' => null,
            'type' => $type,
            'name' => $type->getLabel().' '.fake()->words(2, true),
            'details' => $this->detailsFor($type),
            'license_key' => null,
        ];
    }

    /**
     * Indicate that the item is of the given type, with details for that type.
     */
    public function ofType(ServiceType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'details' => $this->detailsFor($type),
        ]);
    }

    /**
     * Indicate that the item copies the given catalog service.
     */
    public function fromCatalogService(CatalogService $catalogService): static
    {
        return $this->state(fn (array $attributes) => [
            'catalog_service_id' => $catalogService->id,
            'type' => $catalogService->type,
            'name' => $catalogService->name,
            'details' => $this->detailsFor($catalogService->type),
        ]);
    }

    /**
     * Indicate that the item is a license with a license key.
     */
    public function withLicenseKey(): static
    {
        return $this->ofType(ServiceType::License)->state(fn (array $attributes) => [
            'license_key' => strtoupper(fake()->bothify('?????-#####-?????-#####')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function detailsFor(ServiceType $type): array
    {
        return collect($type->customerDetailKeys())
            ->mapWithKeys(fn (string $key) => [$key => fake()->word()])
            ->all();
    }
}
