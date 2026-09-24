<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
        ];
    }
}
