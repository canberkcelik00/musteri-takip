<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Allocations are deleted with the payment. `customer_id` is not fillable:
 * create through Customer::payments().
 *
 * @property int $amount
 * @property Currency $currency
 * @property Carbon $paid_on
 */
#[Fillable(['amount', 'currency', 'paid_on', 'payment_method_id', 'note'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Distinct domain names of the services behind the charges this payment paid down (via its
     * allocations), in the order first seen (T-66, {@see CustomerService::domains()}). A payment
     * with no allocations, or allocated only to charges without a service, has none. Uses the
     * loaded `allocations.charge.customerService.items` relation when available.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        $allocations = $this->relationLoaded('allocations')
            ? $this->allocations
            : $this->allocations()->with('charge.customerService.items')->get();

        return $allocations
            ->map(fn (PaymentAllocation $allocation): ?CustomerService => $allocation->charge?->customerService)
            ->filter()
            ->flatMap(fn (CustomerService $service): array => $service->domains())
            ->unique()
            ->values()
            ->all();
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
            'paid_on' => 'date',
        ];
    }
}
