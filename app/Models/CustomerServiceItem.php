<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\CustomerServiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `details` keeps only the keys of the current type (ServiceType::customerDetailKeys());
 * other keys, including those of a previous type, and blank values are dropped on save.
 * `license_key` is stored encrypted with the application key.
 *
 * @property ServiceType $type
 * @property array<string, mixed>|null $details
 * @property list<string>|null $remote_domains written by the usage refresh, never by a form
 * @property string|null $license_key
 */
#[Fillable(['catalog_service_id', 'server_id', 'remote_key', 'type', 'name', 'details', 'license_key'])]
class CustomerServiceItem extends Model
{
    /** @use HasFactory<CustomerServiceItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (CustomerServiceItem $item): void {
            if ($item->details !== null) {
                $item->details = $item->type->onlyCustomerDetails($item->details);
            }
        });
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * @return BelongsTo<CatalogService, $this>
     */
    public function catalogService(): BelongsTo
    {
        return $this->belongsTo(CatalogService::class);
    }

    /**
     * The server this item was pulled from, if any; `remote_key` identifies it there.
     *
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * The other domains of the hosting account this item is linked to: the main, addon and
     * parked domains the server reported at the last usage refresh (T-119), without the item's
     * own domain. Empty for an item that is not linked or was never refreshed.
     *
     * @return list<string>
     */
    public function otherDomains(): array
    {
        $own = mb_strtolower((string) ($this->details['domain'] ?? ''));

        return array_values(array_filter(
            (array) $this->remote_domains,
            fn (string $domain): bool => $domain !== $own,
        ));
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
            'details' => 'array',
            'remote_domains' => 'array',
            'license_key' => 'encrypted',
        ];
    }
}
