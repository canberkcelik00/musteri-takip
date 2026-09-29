<?php

namespace App\Filament\Widgets;

use App\Actions\Today\SummarizeToday;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers at the top of "Bugün" (docs/UI.md §5), each linking to the same filtered
 * list its count/total is drawn from (SummarizeToday). No polling: the numbers only change
 * through payments and charges recorded elsewhere in the panel, which dispatch the events
 * this refreshes on.
 */
class TodayStats extends BaseWidget
{
    use RefreshesOnPaymentOrChargeChange;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = app(SummarizeToday::class)->handle();

        return [
            Stat::make(__('today.total_debt'), Money::formatTotals($summary['total']))
                ->url(ChargeResource::getUrl('index')),
            Stat::make(__('today.overdue'), Money::formatTotals($summary['overdue']))
                ->description(__('today.overdue_count', ['count' => $summary['overdue_count']]))
                ->color('danger')
                ->url(ChargeResource::getUrl('index', ['filters' => ['only_overdue' => ['isActive' => true]]])),
            Stat::make(__('today.due_this_month'), Money::formatTotals($summary['due_this_month']))
                ->url(ChargeResource::getUrl('index', ['filters' => ['due_this_month' => ['isActive' => true]]])),
            Stat::make(__('today.renewals'), (string) $summary['renewal_customers_count'])
                ->url(CustomerResource::getUrl('index', ['filters' => ['renewal_soon' => ['isActive' => true]]])),
        ];
    }
}
