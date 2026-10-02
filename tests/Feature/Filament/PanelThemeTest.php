<?php

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

beforeEach(function () {
    Filament::setCurrentPanel('panel');
});

it('defaults to light mode with the theme switcher still available', function () {
    $panel = Filament::getCurrentPanel();

    expect($panel->getDefaultThemeMode())->toBe(ThemeMode::Light)
        ->and($panel->hasDarkMode())->toBeTrue()
        ->and($panel->hasThemeSwitcher())->toBeTrue();
});

it('registers the compiled panel theme', function () {
    expect(Filament::getCurrentPanel()->getViteTheme())->toBe('resources/css/filament/panel/theme.css');
});

it('loads Inter locally, without an external font request', function () {
    expect(Filament::getCurrentPanel()->getFontProvider())->toBe(LocalFontProvider::class);
});

it('gives every fixed status meaning its own palette, distinct from primary', function () {
    // Panel::boot() registers the colors with FilamentColor; visiting a panel
    // route triggers it (setCurrentPanel() alone does not).
    $this->get('/panel/login');

    $palettes = FilamentColor::getColors();

    expect($palettes['danger'])->toBe(Color::Red)
        ->and($palettes['warning'])->toBe(Color::Amber)
        ->and($palettes['success'])->toBe(Color::Green)
        ->and($palettes['gray'])->toBe(array_map(Color::convertToOklch(...), AdminPanelProvider::GRAY_SCALE))
        ->and($palettes['primary'])->not->toBe($palettes['danger'])
        ->and($palettes['primary'])->not->toBe($palettes['warning'])
        ->and($palettes['primary'])->not->toBe($palettes['success'])
        ->and($palettes['primary'])->not->toBe($palettes['gray']);
});

it('shows the brand name and logo on the login page', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('Müşteri Takip')
        ->assertSeeHtml('<svg');
});

it('shows the brand name and logo in the panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel')
        ->assertOk()
        ->assertSee('Müşteri Takip')
        ->assertSeeHtml('<svg');
});
