<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use Database\Factories\CatalogServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `details` keeps only the keys of the current type (ServiceType::catalogDetailKeys());
 * other keys, including those of a previous type, and blank values are dropped on save.
 *
 * @property ServiceType $type
 * @property Currency $currency
 * @property array<string, mixed>|null $details
 */
#[Fillable(['server_id', 'remote_package', 'type', 'name', 'price', 'currency', 'details', 'notes'])]
class CatalogService extends Model
{
    /** @use HasFactory<CatalogServiceFactory> */
    use Archivable, HasFactory, TracksUsage;

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
     * A catalog service in a package or sold to a customer is archived, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['packages', 'customerServices', 'customerServiceItems'];
    }

    /**
     * The server whose package this service was pulled from, if any.
     *
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsToMany<Package, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_items')->withTimestamps();
    }

    /**
     * @return HasMany<CustomerService, $this>
     */
    public function customerServices(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    /**
     * @return HasMany<CustomerServiceItem, $this>
     */
    public function customerServiceItems(): HasMany
    {
        return $this->hasMany(CustomerServiceItem::class);
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
