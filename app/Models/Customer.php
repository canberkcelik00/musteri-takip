<?php

namespace App\Models;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\TracksUsage;
use App\Support\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

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
     * Global search (docs/UI.md §2 Kural 12, §7): every word in $term must match the short
     * name, legal title, a contact's name/email/phone, or a domain name kept in
     * customer_service_items.details->domain (of any service, cancelled ones included —
     * a customer stays known by an old domain). Only the domain value is matched, not the
     * raw JSON, so unrelated keys such as "server" never match. JSON_UNQUOTE() returns
     * utf8mb4_bin; it is collated back to utf8mb4_unicode_ci to search case/Turkish-character
     * insensitively, the same as short_name/legal_title's column collation (T-36).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $words = array_filter(preg_split('/\s+/u', trim($term)) ?: []);

        foreach ($words as $word) {
            $like = '%'.$word.'%';

            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->where($this->qualifyColumn('short_name'), 'like', $like)
                    ->orWhere($this->qualifyColumn('legal_title'), 'like', $like)
                    ->orWhereHas('contacts', function (Builder $query) use ($like): void {
                        $query->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhereHas('customerServices.items', function (Builder $query) use ($like): void {
                        $query->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(details, '$.domain')) COLLATE utf8mb4_unicode_ci LIKE ?",
                            [$like],
                        );
                    });
            });
        }
    }

    /**
     * Distinct domain names from every service's items (customer_service_items.details->domain),
     * in the order first seen. Uses the loaded customerServices.items relation; for global
     * search result details, not for the search itself (see {@see self::search()}).
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->customerServices
            ->flatMap(fn (CustomerService $service): Collection => $service->items)
            ->pluck('details.domain')
            ->filter()
            ->unique()
            ->values()
            ->all();
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
     * Not archived and has at least one active service (free ones included, see
     * CustomerService::endingSoon()) ending within the renewal lead time (docs/UI.md §5's
     * "yenilemesi yaklaşan müşteri" stat). A customer counts once regardless of how many of
     * its services qualify.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function renewalSoon(Builder $query): void
    {
        $query
            ->notArchived()
            ->whereHas('customerServices', fn (Builder $query): Builder => $query->endingSoon());
    }

    /**
     * Not archived and has at least one service awaiting a free-continuation decision
     * ({@see CustomerService::awaitingFreeDecision()}). A customer counts once even with
     * several such services.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function awaitingFreeDecision(Builder $query): void
    {
        $query
            ->notArchived()
            ->whereHas('customerServices', fn (Builder $query): Builder => $query->awaitingFreeDecision());
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
