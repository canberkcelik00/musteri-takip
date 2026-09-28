<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The panel's home page ("Bugün"); the daily to-do content is added in G-4.15, as widgets that
 * will use the same RefreshesOnPaymentOrChargeChange trait. Named to override Filament's default
 * Dashboard so the navigation label and title read "Bugün" (docs/UI.md §3) instead of the
 * package default.
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
}
