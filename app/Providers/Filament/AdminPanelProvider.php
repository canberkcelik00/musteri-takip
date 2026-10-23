<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Support\AvatarProviders\InitialsAvatarProvider;
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
     * Apple blue primary scale (docs/UI.md §6): 500 is the button fill, 600 its hover and the
     * link blue, 400 the dark-mode link.
     *
     * @var array<int, string>
     */
    public const PRIMARY_SCALE = [
        50 => '#F0F7FF',
        100 => '#DCEBFC',
        200 => '#B9D8FA',
        300 => '#8BBDF5',
        400 => '#409CFF',
        500 => '#0071E3',
        600 => '#0066CC',
        700 => '#0057AD',
        800 => '#0A4A91',
        900 => '#0B3B73',
        950 => '#082B55',
    ];

    /**
     * Cool neutral gray scale (docs/UI.md §6), anchored to the token table: 50 table head, 100 row
     * divider, 200 border, 600 muted text, 800 dark border, 900 dark panel, 950 dark page.
     *
     * @var array<int, string>
     */
    public const GRAY_SCALE = [
        50 => '#FFFFFF',
        100 => '#E5E5EA',
        200 => '#D1D1D6',
        300 => '#C7C7CC',
        400 => '#AEAEB2',
        500 => '#8E8E93',
        600 => '#6C6C70',
        700 => '#48484A',
        800 => '#38383A',
        900 => '#1C1C1E',
        950 => '#000000',
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
            ->brandLogoHeight('1.75rem')
            ->sidebarWidth('17rem')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->viteTheme('resources/css/filament/panel/theme.css')
            ->defaultThemeMode(ThemeMode::Light)
            // Ctrl+K (⌘K on Mac) focuses the search box from any page (docs/UI.md §2 Kural
            // 12, §7); the suffix shows the binding inside the field itself.
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchFieldKeyBindingSuffix()
            // Below `sm` the search box gives way to a text "Ara" button that opens it over the
            // topbar, with a "Kapat" beside the field (G-4B.7, docs/UI.md §2 Kural 5).
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.panel.search-open')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_END,
                fn (): string => view('filament.panel.search-close')->render(),
            )
            // The bottom tab bar of narrow screens (G-4C.3); only for a signed-in user, so not on the login page.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? view('filament.panel.tab-bar')->render() : '',
            )
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
