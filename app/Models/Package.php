<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\Archivable;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property Currency $currency
 */
#[Fillable(['name', 'price', 'currency', 'notes'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use Archivable, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'TRY',
    ];

    /**
     * @return BelongsToMany<CatalogService, $this>
     */
    public function catalogServices(): BelongsToMany
    {
        return $this->belongsToMany(CatalogService::class, 'package_items')->withTimestamps();
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
