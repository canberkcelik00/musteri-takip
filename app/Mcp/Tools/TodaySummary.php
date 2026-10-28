<?php

namespace App\Mcp\Tools;

use App\Actions\Servers\UsageAlert;
use App\Actions\Today\ListTodayTasks;
use App\Actions\Today\SummarizeToday;
use App\Enums\Permission;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\SiteIncident;
use App\Models\Task;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The "Bugün" page for Claude: the money numbers (only with money.view, as on the page) and the to-do sections of
 * ListTodayTasks, which already leaves out what the user may not see.
 */
#[Description('The "today" overview: open, overdue and this month\'s debt per currency, services up for renewal, and the to-do lists (overdue and soon due charges, free services awaiting a decision, my open tasks, hosting quota warnings, ending SSL certificates, domains pointing elsewhere, sites that are down). Sections the user may not see come back empty.')]
#[IsReadOnly]
#[IsIdempotent]
class TodaySummary extends BaseTool
{
    /**
     * Rows per to-do section; `total` tells how many there are.
     */
    public const int SECTION_ROWS = 10;

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $sections = app(ListTodayTasks::class)->handle($user, self::SECTION_ROWS);

        return Response::structured([
            'today' => today()->toDateString(),
            'money' => $user->can(Permission::MoneyView->value) ? $this->moneySummary() : null,
            'overdue_charges' => [
                'total' => $sections['overdue']['total'],
                'items' => $sections['overdue']['items']->map(fn (Charge $charge): array => self::chargeRow($charge))->all(),
            ],
            'due_soon_charges' => [
                'total' => $sections['due_soon']['total'],
                'items' => $sections['due_soon']['items']->map(fn (Charge $charge): array => self::chargeRow($charge))->all(),
            ],
            'free_services_awaiting_decision' => [
                'total' => $sections['free_decisions']['row_total'],
                'items' => $sections['free_decisions']['items']->map(fn (CustomerService $service): array => [
                    'customer_id' => $service->customer_id,
                    'customer' => $service->customer->short_name,
                    'service' => $service->name,
                    'end_date' => $service->end_date->toDateString(),
                ])->all(),
            ],
            'my_open_tasks' => [
                'total' => $sections['my_tasks']['total'],
                'items' => $sections['my_tasks']['items']->map(fn (Task $task): array => self::taskRow($task))->all(),
            ],
            'quota_warnings' => [
                'total' => $sections['quota']['total'],
                'items' => $sections['quota']['items']->map(fn (UsageAlert $alert): array => [
                    'subject' => $alert->subject,
                    'level' => $alert->level->value,
                    'used' => $alert->used,
                    'limit' => $alert->limit,
                    'unit' => $alert->isBytes ? 'bytes' : 'count',
                    'server' => $alert->serverName,
                    'customers' => array_column($alert->owners, 'short_name'),
                ])->all(),
            ],
            'ssl_ending' => [
                'total' => $sections['ssl']['total'],
                'items' => $sections['ssl']['items']->map(fn (CustomerServiceItem $item): array => [
                    ...$this->itemOwner($item),
                    'ssl_expires_at' => $item->ssl_expires_at?->toDateString(),
                ])->all(),
            ],
            'domains_pointing_elsewhere' => [
                'total' => $sections['moved']['total'],
                'items' => $sections['moved']['items']->map(fn (CustomerServiceItem $item): array => $this->itemOwner($item))->all(),
            ],
            'sites_down' => [
                'total' => $sections['down']['total'],
                'items' => $sections['down']['items']->map(fn (SiteIncident $incident): array => [
                    ...$this->itemOwner($incident->item),
                    'domain' => $incident->domain,
                    'down_since' => $incident->started_at->toDateTimeString(),
                    'reason' => $incident->reason,
                ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function moneySummary(): array
    {
        $summary = app(SummarizeToday::class)->handle();

        return [
            'open_total' => self::totals($summary['total']),
            'overdue' => ['count' => $summary['overdue_count'], 'totals' => self::totals($summary['overdue'])],
            'due_this_month' => ['count' => $summary['due_this_month_count'], 'totals' => self::totals($summary['due_this_month'])],
            'renewals' => [
                'lead_days' => $summary['renewal_lead_days'],
                'services' => $summary['renewal_services_count'],
                'customers' => $summary['renewal_customers_count'],
                'nearest_end_date' => $summary['nearest_renewal']?->toDateString(),
            ],
        ];
    }

    /**
     * @return array{customer_id: int, customer: string, domain: string|null}
     */
    private function itemOwner(CustomerServiceItem $item): array
    {
        $domain = $item->details['domain'] ?? null;

        return [
            'customer_id' => $item->customerService->customer_id,
            'customer' => $item->customerService->customer->short_name,
            'domain' => is_string($domain) ? $domain : null,
        ];
    }
}
