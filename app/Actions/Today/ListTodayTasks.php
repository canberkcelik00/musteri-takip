<?php

namespace App\Actions\Today;

use App\Actions\Servers\ListUsageAlerts;
use App\Actions\Servers\UsageAlert;
use App\Enums\CustomerServiceStatus;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Task;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The "Yapılacaklar" sections of the "Bugün" page (docs/UI.md §5), in priority order: overdue
 * charges (oldest due date first), charges due within 7 days, then free services awaiting a
 * continuation decision, then the user's own open tasks (Faz 13), then hosting quota warnings (Faz 9), then
 * certificates that end within 14 days or already ended (G-16.3), then hostings whose domain points elsewhere (G-16.5).
 * A section the user may not see (money.view, tasks.manage, services.view) comes back empty and runs no query.
 * Each section is capped at $limit rows; `total` is the full count of the underlying list so a "Tümünü gör"
 * link can show how many more there are.
 */
class ListTodayTasks
{
    /**
     * A certificate is listed this many days before it ends.
     */
    public const int SSL_WARNING_DAYS = 14;

    public function __construct(private readonly ListUsageAlerts $listUsageAlerts) {}

    /**
     * @return array{
     *     overdue: array{items: Collection<int, Charge>, total: int},
     *     due_soon: array{items: Collection<int, Charge>, total: int},
     *     free_decisions: array{items: Collection<int, CustomerService>, total: int, row_total: int},
     *     my_tasks: array{items: Collection<int, Task>, total: int},
     *     quota: array{items: SupportCollection<int, UsageAlert>, total: int},
     *     ssl: array{items: Collection<int, CustomerServiceItem>, total: int},
     *     moved: array{items: Collection<int, CustomerServiceItem>, total: int},
     * }
     */
    public function handle(User $user, int $limit = 10): array
    {
        $money = $user->can(Permission::MoneyView->value);

        return [
            'overdue' => $money
                ? $this->chargeSection(fn (Builder $query): Builder => $query->scopes(['overdue', 'unarchivedCustomer']), $limit)
                : ['items' => new Collection, 'total' => 0],
            'due_soon' => $money
                ? $this->chargeSection(fn (Builder $query): Builder => $query->scopes(['dueSoon', 'unarchivedCustomer']), $limit)
                : ['items' => new Collection, 'total' => 0],
            'free_decisions' => $money
                ? $this->freeDecisionSection($limit)
                : ['items' => new Collection, 'total' => 0, 'row_total' => 0],
            'my_tasks' => $user->can(Permission::TasksManage->value)
                ? $this->taskSection($user, $limit)
                : ['items' => new Collection, 'total' => 0],
            'quota' => $user->can(Permission::ServicesView->value)
                ? $this->quotaSection($limit)
                : ['items' => new SupportCollection, 'total' => 0],
            'ssl' => $user->can(Permission::ServicesView->value)
                ? $this->sslSection($limit)
                : ['items' => new Collection, 'total' => 0],
            'moved' => $user->can(Permission::ServicesView->value)
                ? $this->movedSection($limit)
                : ['items' => new Collection, 'total' => 0],
        ];
    }

    /**
     * Hosting items of active services (of customers not archived) whose domain no longer points to their
     * server (G-16.5); an unknown answer (Cloudflare, unreadable) is never listed. Oldest check first.
     *
     * @return array{items: Collection<int, CustomerServiceItem>, total: int}
     */
    private function movedSection(int $limit): array
    {
        $query = fn (): Builder => CustomerServiceItem::query()
            ->where('points_to_server', false)
            ->whereHas('customerService', fn (Builder $services): Builder => $services
                ->where('status', CustomerServiceStatus::Active)
                ->whereHas('customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived'])));

        return [
            'items' => $query()->with('customerService.customer')->orderBy('points_checked_at')->orderBy('id')->limit($limit)->get(),
            'total' => $query()->count(),
        ];
    }

    /**
     * Certificates of active services (of customers not archived) that end within SSL_WARNING_DAYS days or already
     * ended, the soonest first.
     *
     * @return array{items: Collection<int, CustomerServiceItem>, total: int}
     */
    private function sslSection(int $limit): array
    {
        $query = fn (): Builder => CustomerServiceItem::query()
            ->where('ssl_expires_at', '<=', now()->addDays(self::SSL_WARNING_DAYS))
            ->whereHas('customerService', fn (Builder $services): Builder => $services
                ->where('status', CustomerServiceStatus::Active)
                ->whereHas('customer', fn (Builder $customers): Builder => $customers->scopes(['notArchived'])));

        return [
            'items' => $query()->with('customerService.customer')->orderBy('ssl_expires_at')->orderBy('id')->limit($limit)->get(),
            'total' => $query()->count(),
        ];
    }

    /**
     * The user's open tasks in the task list's order (overdue and dated first, undated last).
     *
     * @return array{items: Collection<int, Task>, total: int}
     */
    private function taskSection(User $user, int $limit): array
    {
        $query = fn (): Builder => Task::query()->open()->assignedTo($user);

        return [
            'items' => $query()->orderForList()->with('customer')->limit($limit)->get(),
            'total' => $query()->count(),
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
