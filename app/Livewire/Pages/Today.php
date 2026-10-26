<?php

namespace App\Livewire\Pages;

use App\Actions\Reminders\BuildWhatsAppReminder;
use App\Actions\Scheduler\CheckSchedulerHealth;
use App\Actions\Servers\UsageAlert;
use App\Actions\Today\ListTodayTasks;
use App\Actions\Today\SummarizeToday;
use App\Enums\Permission;
use App\Enums\SchedulerStatus;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Charge;
use App\Models\Customer;
use App\Support\Money;
use App\Support\Ui\Links;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Bugün", the opening page (docs/UI.md §5): the scheduler warning (only when there is a problem), the four numbers —
 * each linking to the filtered list it is drawn from — and the "Yapılacaklar" table in priority order. It only reads
 * (SummarizeToday, ListTodayTasks, CheckSchedulerHealth); every row button opens a window or a link.
 */
#[Layout('layouts.app')]
class Today extends Component
{
    use AuthorizesPermission;

    /**
     * At most this many failing services are named; the rest are counted.
     */
    public const int LISTED_FAILED_SERVICES = 5;

    /**
     * A payment, a charge or a service changed in a window: the numbers and the rows are read again.
     */
    #[On('payments-changed')]
    #[On('charges-changed')]
    #[On('customer-services-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    protected function requiredPermission(): ?Permission
    {
        return Permission::MoneyView;
    }

    public function render(): View
    {
        $summary = app(SummarizeToday::class)->handle();
        $tasks = app(ListTodayTasks::class)->handle();
        $whatsApp = app(BuildWhatsAppReminder::class);

        $charges = (new EloquentCollection([...$tasks['overdue']['items'], ...$tasks['due_soon']['items']]))
            ->load(['customer.primaryContact', 'customerService.items']);

        $totals = $summary['total'];
        $firstTotal = array_shift($totals);

        return view('livewire.pages.today', [
            'dateText' => now()->locale(app()->getLocale())->translatedFormat(__('admin.today_date_format')),
            'health' => $this->health(),
            'stats' => [
                'total' => ['first' => $firstTotal, 'others' => $totals, 'url' => route('app.charges.index', ['hideArchived' => 1])],
                'overdue' => ['totals' => $summary['overdue'], 'count' => $summary['overdue_count'], 'url' => route('app.charges.index', ['onlyOverdue' => 1, 'hideArchived' => 1])],
                'month' => ['totals' => $summary['due_this_month'], 'count' => $summary['due_this_month_count'], 'url' => route('app.charges.index', ['dueThisMonth' => 1, 'hideArchived' => 1])],
                'renewals' => ['days' => $summary['renewal_lead_days'], 'count' => $summary['renewal_services_count'], 'nearest' => $summary['nearest_renewal'], 'url' => route('app.customers.index', ['renewalSoon' => 1])],
            ],
            'rows' => $this->rows($tasks, $whatsApp),
            'taskCount' => $tasks['overdue']['total'] + $tasks['due_soon']['total'] + $tasks['free_decisions']['row_total'] + $tasks['quota']['total'],
            'isEmpty' => $tasks['overdue']['total'] === 0 && $tasks['due_soon']['total'] === 0 && $tasks['free_decisions']['total'] === 0 && $tasks['quota']['total'] === 0,
            'more' => array_values(array_filter([
                ['label' => __('today.section_overdue'), 'url' => route('app.charges.index', ['onlyOverdue' => 1, 'hideArchived' => 1]), 'count' => $tasks['overdue']['total'], 'hidden' => $tasks['overdue']['total'] > count($tasks['overdue']['items'])],
                ['label' => __('today.section_due_soon'), 'url' => route('app.charges.index', ['dueSoon' => 1, 'hideArchived' => 1]), 'count' => $tasks['due_soon']['total'], 'hidden' => $tasks['due_soon']['total'] > count($tasks['due_soon']['items'])],
                ['label' => __('today.section_free_decisions'), 'url' => route('app.customers.index', ['awaitingFreeDecision' => 1]), 'count' => $tasks['free_decisions']['total'], 'hidden' => $tasks['free_decisions']['row_total'] > count($tasks['free_decisions']['items'])],
            ], fn (array $section): bool => $section['hidden'])),
            'quotaMore' => $tasks['quota']['total'] - count($tasks['quota']['items']),
            'charges' => $charges->keyBy('id'),
        ])->title(__('navigation.today'));
    }

    /**
     * The scheduler warning (G-5.6): shown only when there is a problem — it never ran, it stopped over an hour ago
     * or a release waits to be deployed — and for services that failed in its last run.
     *
     * @return array{message: ?string, failed: list<array{url: string, label: string}>, failedCount: int, more: int}
     */
    private function health(): array
    {
        $health = app(CheckSchedulerHealth::class)->handle();
        $failedServices = $health['failed_services'];

        $message = match ($health['status']) {
            SchedulerStatus::Healthy => null,
            SchedulerStatus::NeverRan => __('scheduler.never_ran'),
            SchedulerStatus::Stale => __('scheduler.stale', ['time' => $health['last_run_at']?->format('d.m.Y H:i')]),
            SchedulerStatus::PendingRelease => __('scheduler.pending_release'),
        };

        return [
            'message' => $message,
            'failedCount' => $failedServices->count(),
            'failed' => $failedServices->take(self::LISTED_FAILED_SERVICES)->map(fn ($service): array => [
                'url' => Links::customer($service->customer),
                'label' => $service->customer->short_name.' · '.$service->name,
            ])->values()->all(),
            'more' => max(0, $failedServices->count() - self::LISTED_FAILED_SERVICES),
        ];
    }

    /**
     * The sections as the rows of one table, in priority order (overdue, due soon, free services awaiting a decision,
     * hosting quota warnings).
     *
     * @param  array<string, mixed>  $tasks
     * @return list<array<string, mixed>>
     */
    private function rows(array $tasks, BuildWhatsAppReminder $whatsApp): array
    {
        $rows = [];

        foreach ([...$tasks['overdue']['items'], ...$tasks['due_soon']['items']] as $charge) {
            $rows[] = [
                'type' => 'charge',
                'charge' => $charge,
                'customer' => $charge->customer,
                'subject' => $charge->description.($charge->isPartiallyPaid() ? ' · '.__('today.partially_paid_suffix') : ''),
                'date' => $charge->due_date->format('d.m.Y'),
                'status' => $this->chargeStatusLabel($charge),
                'tone' => $charge->isOverdue() ? 'red' : 'amber',
                'amount' => (new Money($charge->remainingAmount(), $charge->currency))->format(),
                'whatsapp' => $whatsApp->handle($charge),
            ];
        }

        foreach ($tasks['free_decisions']['items'] as $service) {
            $rows[] = [
                'type' => 'free',
                'service' => $service,
                'customer' => $service->customer,
                'subject' => $service->name.' · '.__('today.free_suffix'),
                'date' => $service->end_date->format('d.m.Y'),
                'status' => __('today.awaiting_decision'),
                'tone' => null,
                'amount' => __('money.empty'),
            ];
        }

        $customers = Customer::query()
            ->whereIn('id', $tasks['quota']['items']->reject(fn (UsageAlert $alert): bool => $alert->isServer())->map(fn (UsageAlert $alert): int => $alert->owners[0]['customer_id']))
            ->get()
            ->keyBy('id');

        foreach ($tasks['quota']['items'] as $alert) {
            $tone = $alert->level->getColor() === 'danger' ? 'red' : 'amber';

            if ($alert->isServer()) {
                $rows[] = [
                    'type' => 'server_quota',
                    'server_name' => $alert->serverName,
                    'customer' => null,
                    'subject' => $this->quotaSubject($alert, null),
                    'date' => __('money.empty'),
                    'status' => $alert->level->getLabel(),
                    'tone' => $tone,
                    'amount' => __('money.empty'),
                ];

                continue;
            }

            $owner = $alert->owners[0];

            $rows[] = [
                'type' => 'quota',
                'item_id' => $owner['item_id'],
                'customer' => $customers[$owner['customer_id']],
                'subject' => $this->quotaSubject($alert, $owner['domain']),
                'date' => __('money.empty'),
                'status' => $alert->level->getLabel(),
                'tone' => $tone,
                'amount' => __('money.empty'),
            ];
        }

        return $rows;
    }

    /**
     * "Disk %94 · logi.com.tr" for an account resource, "info@logi.com.tr kotası %97" for a mailbox; with a note when
     * the account is shared by several customers (cPanel).
     */
    private function quotaSubject(UsageAlert $alert, ?string $domain): string
    {
        $subject = $alert->mailboxId !== null
            ? __('today.quota_mailbox', ['address' => $alert->subject, 'percent' => $alert->percent()])
            : __('today.quota_resource', ['resource' => $alert->subject, 'percent' => $alert->percent()]).($domain !== null ? ' · '.$domain : '');

        return count($alert->owners) > 1
            ? $subject.' · '.__('today.quota_shared', ['count' => count($alert->owners)])
            : $subject;
    }

    /**
     * "12 gün gecikti" or "3 gün kaldı" / "Bugün vadesi geliyor" (docs/UI.md §5, §6), the same day counts the charge
     * lists use.
     */
    private function chargeStatusLabel(Charge $charge): string
    {
        if ($charge->isOverdue()) {
            return __('charges.overdue_days', ['count' => $charge->daysOverdue()]);
        }

        $today = Carbon::today(config('app.timezone'));
        $dueDate = Carbon::parse($charge->due_date->toDateString(), config('app.timezone'));
        $days = (int) $today->diffInDays($dueDate);

        return $days <= 0
            ? __('charges.due_today')
            : __('charges.due_in_days', ['count' => $days]);
    }
}
