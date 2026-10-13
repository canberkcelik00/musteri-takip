<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Widgets\SchedulerHealth;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The panel's home page ("Bugün", docs/UI.md §5): the scheduler warning (only when there is a
 * problem, G-5.6), the four stats and the "Yapılacaklar" list, in that order. Named to override Filament's default Dashboard so the navigation label and
 * title read "Bugün" (docs/UI.md §3) instead of the package default.
 */
class Dashboard extends BaseDashboard
{
    use RefreshesOnPaymentOrChargeChange;

    public static function getNavigationLabel(): string
    {
        return __('navigation.today');
    }

    public function getTitle(): string|Htmlable
    {
        return __('navigation.today');
    }

    /**
     * Today's date under the title ("1 Ekim 2026, Perşembe"), as in the design reference.
     */
    public function getSubheading(): string|Htmlable|null
    {
        return now()->locale(app()->getLocale())->translatedFormat(__('admin.today_date_format'));
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            SchedulerHealth::class,
            TodayStats::class,
            TodayTasks::class,
        ];
    }
}
