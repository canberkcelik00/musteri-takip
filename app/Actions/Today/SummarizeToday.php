<?php

namespace App\Actions\Today;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use App\Support\Money;

/**
 * The four numbers at the top of the "Bugün" page (docs/UI.md §5). Each one shares its query
 * with the filtered list its stat links to (ChargesTable's filters, CustomersTable's
 * renewal_soon filter), so the number and the list it opens always agree.
 */
class SummarizeToday
{
    /**
     * @return array{total: array<string, Money>, overdue: array<string, Money>, overdue_count: int, due_this_month: array<string, Money>, renewal_customers_count: int}
     */
    public function handle(): array
    {
        $openCharges = Charge::query()
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->get();

        $overdueCharges = $openCharges->filter(fn (Charge $charge): bool => $charge->isOverdue());
        $dueThisMonthCharges = Charge::query()->dueThisMonth()->withSum('allocations', 'amount')->get();

        return [
            'total' => Money::sumByCurrency($openCharges->map(
                fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency),
            )),
            'overdue' => Money::sumByCurrency($overdueCharges->map(
                fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency),
            )),
            'overdue_count' => $overdueCharges->count(),
            'due_this_month' => Money::sumByCurrency($dueThisMonthCharges->map(
                fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency),
            )),
            'renewal_customers_count' => Customer::query()->renewalSoon()->count(),
        ];
    }
}
