<?php

namespace App\Actions\Charges;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Models\Charge;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The charge list's filters, shared by the "Borçlar" page and Claude's list-charges tool (G-18.2) so both select the
 * same charges for the same choices. A null or empty filter selects everything; the caller adds the order, the
 * eager loads and the page.
 */
class FilterCharges
{
    /**
     * @return Builder<Charge>
     */
    public function handle(
        ?ChargeStatus $status = null,
        bool $onlyOverdue = false,
        bool $dueThisMonth = false,
        bool $dueSoon = false,
        bool $hideArchived = false,
        ?Currency $currency = null,
        ?int $customerId = null,
        string $search = '',
        ?CarbonInterface $dueFrom = null,
        ?CarbonInterface $dueTo = null,
    ): Builder {
        $query = Charge::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($onlyOverdue) {
            $query->overdue();
        }

        if ($dueThisMonth) {
            $query->dueThisMonth();
        }

        if ($dueSoon) {
            $query->dueSoon();
        }

        if ($hideArchived) {
            $query->unarchivedCustomer();
        }

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($dueFrom !== null) {
            $query->whereDate('due_date', '>=', $dueFrom->toDateString());
        }

        if ($dueTo !== null) {
            $query->whereDate('due_date', '<=', $dueTo->toDateString());
        }

        $term = trim($search);

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('description', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customers) => $customers->where('short_name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }
}
