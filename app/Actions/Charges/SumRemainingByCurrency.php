<?php

namespace App\Actions\Charges;

use App\Enums\Currency;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The remaining debt of the charges a query selects, one total per currency (never added across currencies). The
 * sum is made by the database from the query itself, so the number agrees with the list the query filters without
 * loading every charge; a charge with nothing left (or overpaid) adds nothing.
 */
class SumRemainingByCurrency
{
    /**
     * @param  Builder<Charge>  $charges  without `withSum`; this adds the allocated amount itself
     * @return array<string, Money>
     */
    public function handle(Builder $charges): array
    {
        $remaining = 'amount - COALESCE(allocations_sum_amount, 0)';

        $rows = DB::query()
            ->fromSub($charges->withoutEagerLoads()->withSum('allocations', 'amount')->toBase(), 'selected_charges')
            ->selectRaw("currency, SUM({$remaining}) as remaining")
            ->whereRaw("{$remaining} > 0")
            ->groupBy('currency')
            ->get();

        return Money::sumByCurrency(
            $rows->map(fn (object $row): Money => new Money((int) $row->remaining, Currency::from($row->currency))),
        );
    }
}
