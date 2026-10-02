<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Navy primary scale (docs/UI.md §6): 600 is the button, 700 its hover, 400 the dark-mode primary.
     *
     * @var array<int, string>
     */
    public const PRIMARY_SCALE = [
        50 => '#F1F5FB',
        100 => '#E1E9F6',
        200 => '#C6D5EE',
        300 => '#9DB6E2',
        400 => '#6F93D6',
        500 => '#3F68B0',
        600 => '#1D3A6A',
        700 => '#152C52',
        800 => '#10233F',
        900 => '#0C1A30',
        950 => '#08111F',
    ];

    /**
     * Cool neutral gray scale (docs/UI.md §6), anchored to the token table: 50 table head,
     * 100 row divider, 200 border, 600 muted text, 800 dark border, 900 dark panel, 950 dark page.
     *
     * @var array<int, string>
     */
    public const GRAY_SCALE = [
        50 => '#F8F9FB',
        100 => '#EEF0F3',
        200 => '#E0E4EA',
        300 => '#C3CCD9',
        400 => '#8B94A1',
        500 => '#6B7585',
        600 => '#566173',
        700 => '#3B4657',
        800 => '#253142',
        900 => '#151E2B',
        950 => '#0E1520',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('panel')
            ->path('panel')
            ->login()
            ->profile()
            ->brandName(__('admin.brand_name'))
            ->brandLogo(fn () => view('filament.panel.brand'))
            ->brandLogoHeight('1.5rem')
            ->viteTheme('resources/css/filament/panel/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            // Ctrl+K (⌘K on Mac) focuses the search box from any page (docs/UI.md §2 Kural
            // 12, §7); the suffix shows the binding inside the field itself.
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('@livewire(\App\Livewire\TopbarActions::class)'),
            )
            ->navigationGroups([
                NavigationGroup::make()->label(fn (): string => __('navigation.collections')),
                NavigationGroup::make()->label(fn (): string => __('navigation.catalog'))->collapsed(),
                NavigationGroup::make()->label(fn (): string => __('navigation.settings')),
            ])
            ->colors([
                // Buttons and interactive elements only (docs/UI.md §6); the
                // brand color (#ff3800) lives in the theme CSS instead, so it
                // never collides with the status colors below. Primary and gray
                // are fixed scales (G-4B.2), not palettes generated from one color.
                'primary' => self::PRIMARY_SCALE,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'success' => Color::Green,
                'gray' => self::GRAY_SCALE,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
