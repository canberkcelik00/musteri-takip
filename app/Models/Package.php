<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Currency $currency
 */
#[Fillable(['name', 'price', 'currency', 'notes'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use Archivable, HasFactory, TracksUsage;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'TRY',
    ];

    /**
     * A package sold to a customer is archived, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['customerServices'];
    }

    /**
     * @return BelongsToMany<CatalogService, $this>
     */
    public function catalogServices(): BelongsToMany
    {
        return $this->belongsToMany(CatalogService::class, 'package_items')->withTimestamps();
    }

    /**
     * @return HasMany<CustomerService, $this>
     */
    public function customerServices(): HasMany
    {
        return $this->hasMany(CustomerService::class);
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
        ];
    }
}
