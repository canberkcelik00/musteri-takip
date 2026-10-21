<?php

namespace App\Models;

use App\Enums\ServerType;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A WHM reseller or cPanel connection. `api_token` is an API token, not a password, and is stored
 * encrypted with the application key; it is hidden from serialization. A cPanel server needs
 * `catalog_service_id`, the hosting service its domains are assigned with.
 *
 * @property ServerType $type
 * @property string $api_token
 * @property Carbon|null $last_pulled_at
 */
#[Fillable(['name', 'type', 'hostname', 'port', 'username', 'api_token', 'catalog_service_id'])]
#[Hidden(['api_token'])]
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use Archivable, HasFactory, TracksUsage;

    /**
     * A server whose packages or accounts were pulled is archived, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['catalogServices', 'customerServiceItems'];
    }

    /**
     * The hosting service cPanel domains are assigned with.
     *
     * @return BelongsTo<CatalogService, $this>
     */
    public function catalogService(): BelongsTo
    {
        return $this->belongsTo(CatalogService::class);
    }

    /**
     * The stored usage of this server's accounts (a cache of what the server reported).
     *
     * @return HasMany<AccountUsage, $this>
     */
    public function accountUsages(): HasMany
    {
        return $this->hasMany(AccountUsage::class);
    }

    /**
     * @return HasMany<Mailbox, $this>
     */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    /**
     * Catalog services pulled from this server's packages.
     *
     * @return HasMany<CatalogService, $this>
     */
    public function catalogServices(): HasMany
    {
        return $this->hasMany(CatalogService::class);
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
            'type' => ServerType::class,
            'port' => 'integer',
            'api_token' => 'encrypted',
            'last_pulled_at' => 'datetime',
        ];
    }
}
