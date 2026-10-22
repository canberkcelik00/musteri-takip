<?php

namespace App\Actions\Quotes;

use App\Enums\Currency;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Package;

class BuildQuoteItem
{
    /**
     * The columns of a new quote item: name, price and currency are copied from the quoted
     * catalog service or package unless given. A price of zero needs the free flag.
     *
     * @param  array{price?: int, currency?: Currency, is_free?: bool}  $overrides
     * @return array{catalog_service_id: ?int, package_id: ?int, name: string, price: int, currency: Currency, is_free: bool}
     *
     * @throws InvalidQuote
     */
    public function handle(CatalogService|Package $quoted, array $overrides = []): array
    {
        $price = $overrides['price'] ?? $quoted->price;
        $isFree = $overrides['is_free'] ?? false;

        $this->assertPrice($price, $isFree);

        return [
            'catalog_service_id' => $quoted instanceof CatalogService ? $quoted->id : null,
            'package_id' => $quoted instanceof Package ? $quoted->id : null,
            'name' => $quoted->name,
            'price' => $price,
            'currency' => $overrides['currency'] ?? $quoted->currency,
            'is_free' => $isFree,
        ];
    }

    /**
     * @throws InvalidQuote
     */
    public function assertPrice(int $price, bool $isFree): void
    {
        if ($price < 0 || ($price === 0 && ! $isFree)) {
            throw InvalidQuote::priceNotPositive();
        }
    }
}
