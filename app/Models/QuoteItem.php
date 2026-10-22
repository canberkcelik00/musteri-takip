<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\QuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Exactly one of `catalog_service_id` and `package_id` is filled; a CHECK constraint enforces it.
 * Name, price and currency are copies, so later catalog changes do not touch the quote.
 *
 * @property Currency $currency
 * @property bool $is_free
 */
#[Fillable(['catalog_service_id', 'package_id', 'name', 'price', 'currency', 'is_free', 'sort_order'])]
class QuoteItem extends Model
{
    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_free' => false,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<CatalogService, $this>
     */
    public function catalogService(): BelongsTo
    {
        return $this->belongsTo(CatalogService::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * The customer service created when the quote was accepted.
     *
     * @return HasOne<CustomerService, $this>
     */
    public function customerService(): HasOne
    {
        return $this->hasOne(CustomerService::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'currency' => Currency::class,
            'is_free' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
