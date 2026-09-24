<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use Database\Factories\CustomerServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Exactly one of `catalog_service_id` and `package_id` is filled, and `end_date`
 * is after `start_date`; both are enforced by CHECK constraints in the database.
 * `customer_id` is not fillable: create through Customer::customerServices().
 *
 * @property Currency $currency
 * @property CustomerServiceStatus $status
 * @property int $period_months
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon|null $cancelled_on
 */
#[Fillable([
    'catalog_service_id',
    'package_id',
    'name',
    'price',
    'currency',
    'period_months',
    'start_date',
    'end_date',
    'is_free',
    'status',
    'cancelled_on',
    'cancel_note',
    'notes',
])]
class CustomerService extends Model
{
    /** @use HasFactory<CustomerServiceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'period_months' => 12,
        'is_free' => false,
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * @return HasMany<CustomerServiceItem, $this>
     */
    public function items(): HasMany
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
            'price' => 'integer',
            'currency' => Currency::class,
            'period_months' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_free' => 'boolean',
            'status' => CustomerServiceStatus::class,
            'cancelled_on' => 'date',
        ];
    }
}
