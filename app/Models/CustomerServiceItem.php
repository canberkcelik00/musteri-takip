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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'details' => 'array',
            'license_key' => 'encrypted',
        ];
    }
}
