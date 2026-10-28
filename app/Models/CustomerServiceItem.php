<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\CustomerServiceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * `details` keeps only the keys of the current type (ServiceType::customerDetailKeys());
 * other keys, including those of a previous type, and blank values are dropped on save.
 * `license_key` is stored encrypted with the application key.
 *
 * @property ServiceType $type
 * @property array<string, mixed>|null $details
 * @property list<string>|null $remote_domains written by the usage refresh, never by a form
 * @property bool|null $is_cloudflare written by the DNS check, never by a form; null = not checked yet (T-169)
 * @property Carbon|null $dns_checked_at last DNS check attempt, a failed one included
 * @property Carbon|null $ssl_expires_at written by the SSL check, never by a form; null = not read yet (G-16.3)
 * @property Carbon|null $ssl_checked_at last SSL check attempt, a failed one included
 * @property Carbon|null $registration_expires_at day the domain registration ends, written by the WHOIS check, never by a form (G-16.4)
 * @property string|null $registrar who holds the registration, written by the WHOIS check
 * @property Carbon|null $registration_checked_at last WHOIS check attempt, a failed one included
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

            if ($item->exists && $item->isDirty('details') && self::domainOf($item->details) !== self::domainOf($item->getOriginal('details'))) {
                $item->is_cloudflare = null;
                $item->dns_checked_at = null;
                $item->ssl_expires_at = null;
                $item->ssl_checked_at = null;
                $item->registration_expires_at = null;
                $item->registrar = null;
                $item->registration_checked_at = null;
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
     * The item's domain, trimmed and lower case; an empty string when it has none.
     *
     * @param  array<string, mixed>|null  $details
     */
    private static function domainOf(?array $details): string
    {
        return mb_strtolower(trim((string) ($details['domain'] ?? '')));
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
            'is_cloudflare' => 'boolean',
            'dns_checked_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'ssl_checked_at' => 'datetime',
            'registration_expires_at' => 'date',
            'registration_checked_at' => 'datetime',
            'license_key' => 'encrypted',
        ];
    }
}
