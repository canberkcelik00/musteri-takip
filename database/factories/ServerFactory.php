<?php

namespace Database\Factories;

use App\Enums\ServerType;
use App\Enums\ServiceType;
use App\Models\CatalogService;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' WHM',
            'type' => ServerType::Whm,
            'hostname' => fake()->unique()->domainName(),
            'port' => ServerType::Whm->defaultPort(),
            'username' => 'root',
            'api_token' => fake()->regexify('[A-Z0-9]{32}'),
            'catalog_service_id' => null,
        ];
    }

    /**
     * Indicate that the server is a WHM reseller connection.
     */
    public function whm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServerType::Whm,
            'port' => ServerType::Whm->defaultPort(),
            'catalog_service_id' => null,
        ]);
    }

    /**
     * Indicate that the server is a cPanel connection, with the hosting service its domains get.
     */
    public function cpanel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServerType::Cpanel,
            'port' => ServerType::Cpanel->defaultPort(),
            'catalog_service_id' => CatalogService::factory()->ofType(ServiceType::Hosting),
        ]);
    }

    /**
     * Indicate that the server is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
