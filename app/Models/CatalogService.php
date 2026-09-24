<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\Concerns\Archivable;
use Database\Factories\CatalogServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * `details` keeps only the keys of the current type (ServiceType::catalogDetailKeys());
 * other keys, including those of a previous type, are dropped on save.
 *
 * @property ServiceType $type
 * @property array<string, mixed>|null $details
 */
#[Fillable(['type', 'name', 'price', 'currency', 'details', 'notes'])]
class CatalogService extends Model
{
    /** @use HasFactory<CatalogServiceFactory> */
    use Archivable, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'TRY',
    ];

    protected static function booted(): void
    {
        static::saving(function (CatalogService $catalogService): void {
            if ($catalogService->details !== null) {
                $catalogService->details = $catalogService->type->onlyCatalogDetails($catalogService->details);
            }
        });
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_items')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'price' => 'integer',
            'currency' => Currency::class,
            'details' => 'array',
        ];
    }
}
