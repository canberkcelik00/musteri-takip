<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use Carbon\CarbonInterface;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * `customer_service_id` + `period_start` is unique: a period is charged at most once.
 * Remaining amount, partially paid and overdue are derived from the allocations and
 * `due_date`, never stored. `customer_id` is not fillable: create through Customer::charges().
 *
 * @property int $amount
 * @property Currency $currency
 * @property ChargeStatus $status
 * @property Carbon $due_date
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $cancelled_at
 * @property bool $is_renewal
 * @property Carbon|null $reminder_sent_at
 * @property Carbon $created_at
 */
#[Fillable([
    'customer_service_id',
    'description',
    'amount',
    'currency',
    'due_date',
    'period_start',
    'period_end',
    'status',
    'cancelled_at',
    'cancel_reason',
    'is_renewal',
    'cancelled_by',
    'updated_by',
])]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who cancelled the charge; none for one cancelled before Faz 12.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * The user who last edited or split the charge; none when no user ever did.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function customerService(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Total allocated from payments, in minor units. Uses `withSum('allocations', 'amount')`
     * or loaded allocations when available, so lists do not query once per charge.
     */
    public function allocatedAmount(): int
    {
        if (array_key_exists('allocations_sum_amount', $this->attributes)) {
            return (int) $this->attributes['allocations_sum_amount'];
        }

        if ($this->relationLoaded('allocations')) {
            return (int) $this->allocations->sum('amount');
        }

        return (int) $this->allocations()->sum('amount');
    }

    /**
     * Amount still to be paid, in minor units.
     */
    public function remainingAmount(): int
    {
        return $this->amount - $this->allocatedAmount();
    }

    /**
     * An open charge with some, but not all, of its amount allocated.
     */
    public function isPartiallyPaid(): bool
    {
        if ($this->status !== ChargeStatus::Open) {
            return false;
        }

        $allocated = $this->allocatedAmount();

        return $allocated > 0 && $allocated < $this->amount;
    }

    /**
     * An open charge with an unpaid amount from the day after its due date on.
     * The due date itself still counts as open.
     */
    public function isOverdue(): bool
    {
        return $this->daysOverdue() > 0;
    }

    /**
     * Days since the due date for an overdue charge (the day after the due date is 1), otherwise 0.
     * Days are counted in the application time zone (Europe/Istanbul), not the database's.
     */
    public function daysOverdue(): int
    {
        if ($this->status !== ChargeStatus::Open || $this->remainingAmount() <= 0) {
            return 0;
        }

        $today = Carbon::today(config('app.timezone'));
        $dueDate = Carbon::parse($this->due_date->toDateString(), config('app.timezone'));

        return max(0, (int) $dueDate->diffInDays($today, false));
    }

    /**
     * Days ahead an open charge counts as "due soon" for display (docs/UI.md §5, §6).
     */
    public const DUE_SOON_DAYS = 7;

    /**
     * An open, not-yet-overdue charge whose due date falls within the next
     * {@see self::DUE_SOON_DAYS} days (today included). Purely a display state:
     * it is derived on every call, never stored, and does not affect status or amount.
     */
    public function isDueSoon(): bool
    {
        if ($this->status !== ChargeStatus::Open || $this->isOverdue()) {
            return false;
        }

        $today = Carbon::today(config('app.timezone'));
        $dueDate = Carbon::parse($this->due_date->toDateString(), config('app.timezone'));

        return $dueDate->lte($today->copy()->addDays(self::DUE_SOON_DAYS));
    }

    /**
     * Query-level equivalent of {@see self::isDueSoon()}. An open charge's due date
     * already guarantees it is unpaid (see SyncChargeStatus), so no amount filter is needed.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function dueSoon(Builder $query): void
    {
        $today = Carbon::today(config('app.timezone'));

        $query
            ->where($this->qualifyColumn('status'), ChargeStatus::Open)
            ->whereBetween($this->qualifyColumn('due_date'), [$today, $today->copy()->addDays(self::DUE_SOON_DAYS)]);
    }

    /**
     * Query-level equivalent of {@see self::isOverdue()}. Like {@see self::dueSoon()}, no
     * amount filter is needed: an open charge's due date already guarantees it is unpaid
     * (SyncChargeStatus moves a fully allocated charge to `paid`), so a partially paid open
     * charge counts as overdue here too.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $today = Carbon::today(config('app.timezone'));

        $query
            ->where($this->qualifyColumn('status'), ChargeStatus::Open)
            ->whereDate($this->qualifyColumn('due_date'), '<', $today);
    }

    /**
     * Leave out the charges of archived customers: archived records disappear from the
     * "Bugün" page, its filtered lists and the digest (docs/PRD.md, T-96). The one archive
     * rule: every "Bugün" number and digest section adds it to its own scope, and the charge
     * list's "Arşivli müşterileri gizle" filter, which the "Bugün" links turn on, applies it.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function unarchivedCustomer(Builder $query): void
    {
        $query->whereHas('customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived']));
    }

    /**
     * Open charges due from today through the end of the current calendar month
     * (docs/UI.md §5's "bu ay vadesi gelen"), overdue ones counted separately and not here.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function dueThisMonth(Builder $query): void
    {
        $today = Carbon::today(config('app.timezone'));

        $query
            ->where($this->qualifyColumn('status'), ChargeStatus::Open)
            ->whereBetween($this->qualifyColumn('due_date'), [$today, $today->copy()->endOfMonth()]);
    }

    /**
     * Limit the query to charges created on the given day, counted in the application
     * time zone (Europe/Istanbul). The daily digest lists these as the day's new charges.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function createdOn(Builder $query, CarbonInterface $date): void
    {
        $day = Carbon::instance($date)->setTimezone(config('app.timezone'));

        $query->whereBetween($this->qualifyColumn('created_at'), [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    /**
     * Limit the query to charges created in [$since, $until): from $since on, before $until.
     * Consecutive windows that share their boundary neither miss nor repeat a charge, even one
     * stored in the same second as the boundary (`created_at` has second precision). The
     * moments are compared in the time zone `created_at` is stored in (the application time
     * zone). The digest lists these as the charges created since the previous digest.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function createdBetween(Builder $query, CarbonInterface $since, CarbonInterface $until): void
    {
        $timezone = config('app.timezone');

        $query
            ->where($this->qualifyColumn('created_at'), '>=', Carbon::instance($since)->setTimezone($timezone))
            ->where($this->qualifyColumn('created_at'), '<', Carbon::instance($until)->setTimezone($timezone));
    }

    /**
     * Renewal charges (G-3.4) the customer has not been reminded of yet and that are still
     * worth a reminder (G-5B.1): open, due today or later, of an active, non-free service of
     * an unarchived customer. Whether the primary contact has a usable address is not decided
     * here; the reminder sender and the digest warning share that check.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function awaitingReminder(Builder $query): void
    {
        $query
            ->where($this->qualifyColumn('is_renewal'), true)
            ->where($this->qualifyColumn('status'), ChargeStatus::Open)
            ->whereDate($this->qualifyColumn('due_date'), '>=', Carbon::today(config('app.timezone')))
            ->whereNull($this->qualifyColumn('reminder_sent_at'))
            ->whereHas('customerService', fn (Builder $services): Builder => $services
                ->where('status', CustomerServiceStatus::Active)
                ->where('is_free', false))
            ->unarchivedCustomer();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'currency' => Currency::class,
            'due_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => ChargeStatus::class,
            'cancelled_at' => 'datetime',
            'is_renewal' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }
}
