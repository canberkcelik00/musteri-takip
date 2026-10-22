<?php

namespace Database\Factories;

use App\Models\CatalogService;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default a single catalog service is quoted; name, price and currency are copied from
 * the quoted catalog service or package, as when a quote is created.
 *
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'catalog_service_id' => CatalogService::factory(),
            'package_id' => null,
            'name' => fn (array $attributes) => $this->quotedItem($attributes)->name,
            'price' => fn (array $attributes) => $this->quotedItem($attributes)->price,
            'currency' => fn (array $attributes) => $this->quotedItem($attributes)->currency,
            'is_free' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that a package is quoted instead of a single catalog service.
     */
    public function forPackage(): static
    {
        return $this->state(fn (array $attributes) => [
            'catalog_service_id' => null,
            'package_id' => Package::factory(),
        ]);
    }

    /**
     * Indicate that the item is free of charge.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 0,
            'is_free' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function quotedItem(array $attributes): CatalogService|Package
    {
        return $attributes['package_id'] !== null
            ? Package::findOrFail($attributes['package_id'])
            : CatalogService::findOrFail($attributes['catalog_service_id']);
    }
}
