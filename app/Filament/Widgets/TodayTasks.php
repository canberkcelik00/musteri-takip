<?php

namespace App\Filament\Widgets;

use App\Actions\Servers\UsageAlert;
use App\Actions\Today\ListTodayTasks;
use App\Filament\Actions\ConfirmFreeContinuationAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\RemoveFreeFlagAction;
use App\Filament\Actions\ViewUsageAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Servers\ServerResource;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The "Yapılacaklar" list on "Bugün" (docs/UI.md §5): four sections in priority order (the last
 * one, hosting quota warnings, has no filtered list to link to), each
 * capped at 10 rows with a "Tümünü gör" link to its filtered list when there are more. Only
 * calls ListTodayTasks for its data and the shared row Actions (RecordPaymentAction,
 * RemoveFreeFlagAction, ConfirmFreeContinuationAction) for its buttons; no business logic here.
 */
class TodayTasks extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use RefreshesOnPaymentOrChargeChange;

    protected string $view = 'filament.widgets.today-tasks';

    protected int|string|array $columnSpan = 'full';

    /**
     * The free-decision services of the rows being rendered, handed to the row actions so they
     * do not look each service up again (set while the view data is built; not Livewire state).
     *
     * @var Collection<int, CustomerService>|null
     */
    protected ?Collection $freeServices = null;

    /**
     * The row buttons are secondary (gray, text only; G-4B.5, docs/UI.md §4 "Birincil düğme
     * kuralı"): the one primary button of the page is "Ödeme al" in the top bar.
     */
    public function recordPaymentAction(): Action
    {
        return RecordPaymentAction::make()
            ->color('gray')
            ->icon(null);
    }

    public function removeFreeAction(): Action
    {
        return RemoveFreeFlagAction::make()
            ->knownServices($this->freeServices)
            ->color('gray')
            ->icon(null);
    }

    public function confirmFreeContinuationAction(): Action
    {
        return ConfirmFreeContinuationAction::make()
            ->knownServices($this->freeServices)
            ->color('gray')
            ->icon(null);
    }

    /**
     * "Kullanımı gör" of a quota row: the "Kullanım" window opens here on Bugün, so the work is
     * done without going to the customer's card.
     */
    public function viewUsageAction(): Action
    {
        return ViewUsageAction::make()->icon(null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tasks = app(ListTodayTasks::class)->handle();
        $this->freeServices = $tasks['free_decisions']['items']->keyBy('id');

        return [
            ...$tasks,
            'rows' => $this->rows($tasks),
            'taskCount' => $tasks['overdue']['total'] + $tasks['due_soon']['total'] + $tasks['free_decisions']['row_total'] + $tasks['quota']['total'],
            'isEmpty' => $tasks['overdue']['total'] === 0 && $tasks['due_soon']['total'] === 0 && $tasks['free_decisions']['total'] === 0 && $tasks['quota']['total'] === 0,
            'overdueUrl' => ChargeResource::getUrlWithoutArchived(['only_overdue' => ['isActive' => true]]),
            'dueSoonUrl' => ChargeResource::getUrlWithoutArchived(['due_soon' => ['isActive' => true]]),
            'serversUrl' => ServerResource::getUrl('index'),
            'freeDecisionsUrl' => CustomerResource::getUrl('index', ['filters' => ['awaiting_free_decision' => ['isActive' => true]]]),
        ];
    }

    /**
     * The three sections as the rows of one table, in priority order (overdue, due soon, free
     * services awaiting a decision).
     *
     * @param  array<string, mixed>  $tasks
     * @return list<array<string, mixed>>
     */
    private function rows(array $tasks): array
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
                'color' => $charge->isOverdue() ? 'danger' : 'warning',
                'amount' => $this->chargeAmount($charge),
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
                'color' => null,
                'amount' => __('money.empty'),
            ];
        }

        $customers = Customer::query()
            ->whereIn('id', $tasks['quota']['items']->reject(fn (UsageAlert $alert): bool => $alert->isServer())->map(fn (UsageAlert $alert): int => $alert->owners[0]['customer_id']))
            ->get()
            ->keyBy('id');

        foreach ($tasks['quota']['items'] as $alert) {
            if ($alert->isServer()) {
                $rows[] = [
                    'type' => 'server_quota',
                    'server_name' => $alert->serverName,
                    'customer' => null,
                    'subject' => $this->quotaSubject($alert, null),
                    'date' => __('money.empty'),
                    'status' => $alert->level->getLabel(),
                    'color' => $alert->level->getColor(),
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
                'color' => $alert->level->getColor(),
                'amount' => __('money.empty'),
            ];
        }

        return $rows;
    }

    /**
     * "Disk %94 · logi.com.tr" for an account resource, "info@logi.com.tr kotası %97" for a
     * mailbox; with a note when the account is shared by several customers (cPanel).
     *
     * @param  string|null  $domain  the first owner's domain
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
     * "12 gün gecikti"or "3 gün kaldı" / "Bugün vadesi geliyor" (docs/UI.md §5, §6), the same
     * day counts ChargeStatusBadge uses for the charge lists.
     */
    public function chargeStatusLabel(Charge $charge): string
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

    public function chargeAmount(Charge $charge): string
    {
        return (new Money($charge->remainingAmount(), $charge->currency))->format();
    }

    public function customerUrl(Customer $customer): string
    {
        return CustomerResource::getUrl('view', ['record' => $customer]);
    }
}
