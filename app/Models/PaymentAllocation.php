<?php

namespace App\Models;

use App\Enums\AllocationSource;
use Carbon\CarbonInterface;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The part of a payment applied to a charge. `payment_id` is not fillable:
 * create through Payment::allocations().
 *
 * `payment_id` + `charge_id` + `source` is unique: an allocation made while the payment
 * was recorded and one applied later from the credit balance stay on separate rows.
 *
 * @property int $amount
 * @property AllocationSource $source
 * @property Carbon $created_at
 */
#[Fillable(['charge_id', 'amount', 'source'])]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'payment',
    ];

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * Limit the query to allocations applied from the credit balance on the given day,
     * counted in the application time zone (Europe/Istanbul).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function appliedFromCreditBalanceOn(Builder $query, CarbonInterface $date): void
    {
        $day = Carbon::instance($date)->setTimezone(config('app.timezone'));

        $query->where($this->qualifyColumn('source'), AllocationSource::CreditBalance)
            ->whereBetween($this->qualifyColumn('created_at'), [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
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
            'source' => AllocationSource::class,
        ];
    }
}
