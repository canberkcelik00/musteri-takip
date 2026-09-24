<?php

namespace Database\Factories;

use App\Enums\CustomerServiceStatus;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * By default a single catalog service is sold. Name, price and currency are copied
 * from the sold catalog service or package, and the end date is start date plus
 * `period_months` without overflow (29.02 → 28.02). After creating, items are
 * added as on assignment (one per catalog service) unless items already exist.
 *
 * @extends Factory<CustomerService>
 */
class CustomerServiceFactory extends Factory
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
            'catalog_service_id' => CatalogService::factory(),
            'package_id' => null,
            'name' => fn (array $attributes) => $this->soldItem($attributes)->name,
            'price' => fn (array $attributes) => $this->soldItem($attributes)->price,
            'currency' => fn (array $attributes) => $this->soldItem($attributes)->currency,
            'period_months' => 12,
            'start_date' => now()->subDays(fake()->numberBetween(0, 330))->toDateString(),
            'end_date' => fn (array $attributes) => Carbon::parse($attributes['start_date'])
                ->addMonthsNoOverflow($attributes['period_months'])
                ->toDateString(),
            'is_free' => false,
            'status' => CustomerServiceStatus::Active,
            'notes' => null,
        ];
    }

    /**
     * Create the items of the sold catalog service or package, as on assignment.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CustomerService $customerService): void {
            if ($customerService->items()->exists()) {
                return;
            }

            $catalogServices = $customerService->package_id !== null
                ? $customerService->package()->firstOrFail()->catalogServices
                : collect([$customerService->catalogService()->firstOrFail()]);

            foreach ($catalogServices as $catalogService) {
                CustomerServiceItem::factory()
                    ->for($customerService)
                    ->fromCatalogService($catalogService)
                    ->create();
            }
        });
    }

    /**
     * Indicate that a single catalog service is sold.
     */
    public function forCatalogService(?CatalogService $catalogService = null): static
    {
        return $this->state(fn (array $attributes) => [
            'catalog_service_id' => $catalogService->id ?? CatalogService::factory(),
            'package_id' => null,
        ]);
    }

    /**
     * Indicate that a package is sold (by default a new package with three catalog services).
     */
    public function forPackage(?Package $package = null): static
    {
        return $this->state(fn (array $attributes) => [
            'catalog_service_id' => null,
            'package_id' => $package->id ?? Package::factory()->withCatalogServices(),
        ]);
    }

    /**
     * Indicate that the customer service is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }

    /**
     * Indicate that the customer service is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerServiceStatus::Cancelled,
            'cancelled_on' => now()->toDateString(),
            'cancel_note' => 'Müşteri hizmeti yenilemek istemedi.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function soldItem(array $attributes): CatalogService|Package
    {
        return $attributes['package_id'] !== null
            ? Package::findOrFail($attributes['package_id'])
            : CatalogService::findOrFail($attributes['catalog_service_id']);
    }
}
