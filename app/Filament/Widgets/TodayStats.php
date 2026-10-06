<?php

namespace App\Filament\Widgets;

use App\Actions\Today\SummarizeToday;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Providers\AppServiceProvider;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers at the top of "Bugün" (docs/UI.md §5), each linking to the same filtered
 * list its count/total is drawn from (SummarizeToday). No polling: the numbers only change
 * through payments and charges recorded elsewhere in the panel, which dispatch the events
 * this refreshes on. Wording follows the reference screen (G-4B.6): the first currency is the
 * big number and any other currency the "+ …" line below it; the renewal card counts services
 * and links to their customers (SummarizeToday).
 */
class TodayStats extends BaseWidget
{
    use RefreshesOnPaymentOrChargeChange;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $summary = app(SummarizeToday::class)->handle();

        $totals = $summary['total'];
        $firstTotal = array_shift($totals);

        return [
            Stat::make(__('today.total_debt'), $firstTotal?->format() ?? Money::formatTotals([]))
                ->description($totals === [] ? null : '+ '.Money::formatTotals($totals))
                ->url(ChargeResource::getUrl('index')),
            Stat::make(__('today.overdue'), Money::formatTotals($summary['overdue']))
                ->description(__('today.charge_count', ['count' => $summary['overdue_count']]))
                ->color('danger')
                ->url(ChargeResource::getUrl('index', ['filters' => ['only_overdue' => ['isActive' => true]]])),
            Stat::make(__('today.due_this_month'), Money::formatTotals($summary['due_this_month']))
                ->description($summary['due_this_month_count'] === 0 ? null : __('today.charge_count', ['count' => $summary['due_this_month_count']]))
                ->url(ChargeResource::getUrl('index', ['filters' => ['due_this_month' => ['isActive' => true]]])),
            Stat::make(
                __('today.renewals', ['days' => $summary['renewal_lead_days']]),
                __('today.service_count', ['count' => $summary['renewal_services_count']]),
            )
                ->description($summary['nearest_renewal'] === null ? null : __('today.nearest_renewal', [
                    'date' => $summary['nearest_renewal']->format(AppServiceProvider::DATE_FORMAT),
                ]))
                ->url(CustomerResource::getUrl('index', ['filters' => ['renewal_soon' => ['isActive' => true]]])),
        ];
    }
}
