<?php

namespace App\Actions\Today;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The four numbers at the top of the "Bugün" page (docs/UI.md §5). Each one shares its query
 * with the filtered list its stat links to (ChargesTable's filters, CustomersTable's
 * renewal_soon filter), so the number and the list it opens always agree. The one exception is
 * the renewal card (G-4B.6, docs/UI.md §5): it counts services ending soon, while its link opens
 * the customers who own them (a customer with two such services is one row there).
 */
class SummarizeToday
{
    /**
     * @return array{total: array<string, Money>, overdue: array<string, Money>, overdue_count: int, due_this_month: array<string, Money>, due_this_month_count: int, renewal_customers_count: int, renewal_services_count: int, nearest_renewal: ?Carbon, renewal_lead_days: int}
     */
    public function handle(): array
    {
        $openCharges = Charge::query()
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->get();

        $overdueCharges = $openCharges->filter(fn (Charge $charge): bool => $charge->isOverdue());
        $dueThisMonthCharges = Charge::query()->dueThisMonth()->withSum('allocations', 'amount')->get();

        $renewingServices = CustomerService::query()
            ->endingSoon()
            ->whereHas('customer', fn (Builder $query): Builder => $query->notArchived());
        $nearestRenewal = (clone $renewingServices)->min('end_date');

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
            'due_this_month_count' => $dueThisMonthCharges->count(),
            'renewal_customers_count' => Customer::query()->renewalSoon()->count(),
            'renewal_services_count' => $renewingServices->count(),
            'nearest_renewal' => $nearestRenewal === null ? null : Carbon::parse($nearestRenewal),
            'renewal_lead_days' => app(Settings::class)->renewalLeadDays(),
        ];
    }
}
