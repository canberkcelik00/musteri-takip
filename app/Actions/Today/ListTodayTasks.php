<?php

namespace App\Actions\Today;

use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\UsageAlert;
use App\Models\Charge;
use App\Models\CustomerService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The "Yapılacaklar" sections of the "Bugün" page (docs/UI.md §5), in priority order: overdue
 * charges (oldest due date first), charges due within 7 days, then free services awaiting a
 * continuation decision, then hosting quota warnings (Faz 9). Each section is capped at $limit rows; `total` is the full count of
 * the underlying list so a "Tümünü gör" link can show how many more there are.
 */
class ListTodayTasks
{
    public function __construct(private readonly ListUsageAlerts $listUsageAlerts) {}

    /**
     * @return array{
     *     overdue: array{items: Collection<int, Charge>, total: int},
     *     due_soon: array{items: Collection<int, Charge>, total: int},
     *     free_decisions: array{items: Collection<int, CustomerService>, total: int, row_total: int},
     *     quota: array{items: SupportCollection<int, UsageAlert>, total: int},
     * }
     */
    public function handle(int $limit = 10): array
    {
        return [
            'overdue' => $this->chargeSection(fn (Builder $query): Builder => $query->scopes(['overdue', 'unarchivedCustomer']), $limit),
            'due_soon' => $this->chargeSection(fn (Builder $query): Builder => $query->scopes(['dueSoon', 'unarchivedCustomer']), $limit),
            'free_decisions' => $this->freeDecisionSection($limit),
            'quota' => $this->quotaSection($limit),
        ];
    }

    /**
     * Hosting resources at or above the usage warning threshold, fullest first (docs/UI.md
     * "Kullanım penceresi"); the last section, after everything that involves money.
     *
     * @return array{items: SupportCollection<int, UsageAlert>, total: int}
     */
    private function quotaSection(int $limit): array
    {
        $alerts = $this->listUsageAlerts->handle();

        return ['items' => $alerts->take($limit)->values(), 'total' => $alerts->count()];
    }

    /**
     * @return array{items: Collection<int, Charge>, total: int}
     */
    private function chargeSection(Closure $scope, int $limit): array
    {
        $total = $scope(Charge::query())->count();

        $items = $scope(Charge::query())
            ->with('customer')
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Rows are services, but `total` counts distinct customers (docs/UI.md §5, matching
     * CustomersTable's awaiting_free_decision filter and stat link): a customer with several
     * qualifying services counts once. `row_total` counts the (unlimited) service rows
     * instead, so "Tümünü gör" appears exactly when rows were left out, independently of how
     * many customers those rows belong to.
     *
     * @return array{items: Collection<int, CustomerService>, total: int, row_total: int}
     */
    private function freeDecisionSection(int $limit): array
    {
        $rowTotal = $this->freeDecisionQuery()->count();
        $total = $this->freeDecisionQuery()->pluck('customer_id')->unique()->count();

        $items = $this->freeDecisionQuery()
            ->with('customer')
            ->orderBy('end_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return ['items' => $items, 'total' => $total, 'row_total' => $rowTotal];
    }

    /**
     * @return Builder<CustomerService>
     */
    private function freeDecisionQuery(): Builder
    {
        return CustomerService::query()
            ->scopes(['awaitingFreeDecision'])
            ->whereHas('customer', fn (Builder $query): Builder => $query->scopes(['notArchived']));
    }
}
