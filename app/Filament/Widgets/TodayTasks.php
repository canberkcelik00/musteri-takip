<?php

namespace App\Filament\Widgets;

use App\Actions\Today\ListTodayTasks;
use App\Filament\Actions\ConfirmFreeContinuationAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Actions\RemoveFreeFlagAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Charge;
use App\Models\Customer;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * The "Yapılacaklar" list on "Bugün" (docs/UI.md §5): three sections in priority order, each
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

    public function recordPaymentAction(): Action
    {
        return RecordPaymentAction::make()
            ->color('primary');
    }

    public function removeFreeAction(): Action
    {
        return RemoveFreeFlagAction::make();
    }

    public function confirmFreeContinuationAction(): Action
    {
        return ConfirmFreeContinuationAction::make();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $tasks = app(ListTodayTasks::class)->handle();

        return [
            ...$tasks,
            'isEmpty' => $tasks['overdue']['total'] === 0 && $tasks['due_soon']['total'] === 0 && $tasks['free_decisions']['total'] === 0,
            'overdueUrl' => ChargeResource::getUrl('index', ['filters' => ['only_overdue' => ['isActive' => true]]]),
            'dueSoonUrl' => ChargeResource::getUrl('index', ['filters' => ['due_soon' => ['isActive' => true]]]),
            'freeDecisionsUrl' => CustomerResource::getUrl('index', ['filters' => ['awaiting_free_decision' => ['isActive' => true]]]),
        ];
    }

    /**
     * "12 gün gecikti" or "3 gün kaldı" / "Bugün vadesi geliyor" (docs/UI.md §5, §6), the same
     * day counts ChargeStatusColumn uses for the charge lists.
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
