<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Models\Concerns\TracksUsage;
use App\Support\Settings;
use Database\Factories\CustomerServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
    use HasFactory, TracksUsage;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'period_months' => 12,
        'is_free' => false,
        'status' => 'active',
    ];

    /**
     * A service with a charge (cancelled ones included) is cancelled, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['charges'];
    }

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
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * An active service whose end date falls within the next $leadDays days (today
     * included). Purely a display state: it is derived on every call, never stored.
     * Defaults to the configured renewal lead time (docs/UI.md §5, §6).
     */
    public function isEndingSoon(?int $leadDays = null): bool
    {
        if ($this->status !== CustomerServiceStatus::Active) {
            return false;
        }

        $leadDays ??= app(Settings::class)->renewalLeadDays();

        $today = Carbon::today(config('app.timezone'));
        $endDate = Carbon::parse($this->end_date->toDateString(), config('app.timezone'));

        return $endDate->between($today, $today->copy()->addDays($leadDays));
    }

    /**
     * Query-level equivalent of {@see self::isEndingSoon()}.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function endingSoon(Builder $query, ?int $leadDays = null): void
    {
        $leadDays ??= app(Settings::class)->renewalLeadDays();

        $today = Carbon::today(config('app.timezone'));

        $query
            ->where($this->qualifyColumn('status'), CustomerServiceStatus::Active)
            ->whereBetween($this->qualifyColumn('end_date'), [$today, $today->copy()->addDays($leadDays)]);
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
