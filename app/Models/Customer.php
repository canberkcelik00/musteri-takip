<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use App\Support\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['short_name', 'legal_title', 'tax_office', 'tax_number', 'billing_address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Archivable, HasFactory, TracksUsage;

    /**
     * A customer with a service, charge or payment is archived, not deleted.
     *
     * @return list<string>
     */
    protected function usageRelations(): array
    {
        return ['customerServices', 'charges', 'payments'];
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasOne<Contact, $this>
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<CustomerService, $this>
     */
    public function customerServices(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Open debt per currency: the remaining amount of every open charge, summed per currency.
     * Currencies without open debt are left out. Uses the loaded `charges` relation (with
     * `withSum('allocations', 'amount')`) when available, so lists do not query per customer.
     *
     * @return array<string, Money>
     */
    public function openChargeTotals(): array
    {
        $charges = $this->relationLoaded('charges')
            ? $this->charges
            : $this->charges()->where('status', ChargeStatus::Open)->withSum('allocations', 'amount')->get();

        return Money::sumByCurrency(
            $charges
                ->filter(fn (Charge $charge): bool => $charge->status === ChargeStatus::Open && $charge->remainingAmount() > 0)
                ->map(fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency)),
        );
    }

    /**
     * Credit balance per currency: payments minus their allocations. Currencies without
     * a balance are left out; keyed by currency value in `Currency::cases()` order.
     *
     * @return array<string, Money>
     */
    public function creditBalances(): array
    {
        $paid = $this->payments()
            ->groupBy('currency')
            ->selectRaw('currency, SUM(amount) as total')
            ->pluck('total', 'currency');

        $allocated = PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.customer_id', $this->getKey())
            ->groupBy('payments.currency')
            ->selectRaw('payments.currency as currency, SUM(payment_allocations.amount) as total')
            ->pluck('total', 'currency');

        $balances = [];

        foreach (Currency::cases() as $currency) {
            $balance = (int) ($paid[$currency->value] ?? 0) - (int) ($allocated[$currency->value] ?? 0);

            if ($balance !== 0) {
                $balances[$currency->value] = new Money($balance, $currency);
            }
        }

        return $balances;
    }
}
