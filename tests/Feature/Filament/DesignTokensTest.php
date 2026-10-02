<?php

use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/*
 * Design tokens (G-4B.2, docs/UI.md §6): every text/background pair reaches WCAG AA (4.5:1) in
 * both themes, literal colors live only in the token block of the theme CSS, and Filament gets
 * the fixed primary and gray scales.
 */

function designThemeCss(): string
{
    return (string) file_get_contents(resource_path('css/filament/panel/theme.css'));
}

/**
 * The body of the token rule with the given selector (`:root` or `:root.dark`).
 */
function designTokenBlock(string $selector): string
{
    $found = preg_match('/^'.preg_quote($selector, '/').'\s*\{(.*?)^\}/ms', designThemeCss(), $matches);

    expect($found)->toBe(1, "Token block {$selector} is missing from the theme CSS.");

    return $matches[1];
}

/**
 * @return array<string, string>
 */
function designTokens(string $theme): array
{
    $tokens = [];

    foreach ($theme === 'dark' ? [':root', ':root.dark'] : [':root'] as $selector) {
        preg_match_all('/--(mt-[a-z0-9-]+)\s*:\s*([^;]+);/', designTokenBlock($selector), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $name, $value]) {
            $tokens[$name] = trim($value);
        }
    }

    return $tokens;
}

/**
 * WCAG 2 relative luminance of a `#rrggbb` color.
 */
function designLuminance(string $hex): float
{
    $channels = array_map(
        function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        },
        str_split(ltrim($hex, '#'), 2),
    );

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function designContrast(string $foreground, string $background): float
{
    $lighter = max(designLuminance($foreground), designLuminance($background));
    $darker = min(designLuminance($foreground), designLuminance($background));

    return ($lighter + 0.05) / ($darker + 0.05);
}

dataset('text pairs', [
    'metin / sayfa' => ['mt-text', 'mt-page-bg'],
    'metin / panel' => ['mt-text', 'mt-panel-bg'],
    'metin / tablo başlığı' => ['mt-text', 'mt-table-head-bg'],
    'soluk metin / sayfa' => ['mt-text-muted', 'mt-page-bg'],
    'soluk metin / panel' => ['mt-text-muted', 'mt-panel-bg'],
    'soluk metin / tablo başlığı' => ['mt-text-muted', 'mt-table-head-bg'],
    'menü metni / menü' => ['mt-sidebar-text', 'mt-sidebar-bg'],
    'menü grup başlığı / menü' => ['mt-sidebar-group', 'mt-sidebar-bg'],
    'seçili menü metni / seçili zemin' => ['mt-sidebar-active-text', 'mt-sidebar-active-bg'],
    'düğme metni / primary' => ['mt-on-primary', 'mt-primary'],
    'düğme metni / primary hover' => ['mt-on-primary', 'mt-primary-hover'],
    'primary bağlantı / panel' => ['mt-primary', 'mt-panel-bg'],
    'tehlikeli düğme metni / zemin' => ['mt-danger-text', 'mt-danger-bg'],
    'kırmızı rozet' => ['mt-badge-red-text', 'mt-badge-red-bg'],
    'amber rozet' => ['mt-badge-amber-text', 'mt-badge-amber-bg'],
    'yeşil rozet' => ['mt-badge-green-text', 'mt-badge-green-bg'],
    'gri rozet' => ['mt-badge-gray-text', 'mt-badge-gray-bg'],
]);

it('keeps text contrast at least 4.5:1', function (string $theme, string $foreground, string $background) {
    $tokens = designTokens($theme);

    expect($tokens)->toHaveKeys([$foreground, $background])
        ->and($tokens[$foreground])->toMatch('/^#[0-9a-f]{6}$/i')
        ->and($tokens[$background])->toMatch('/^#[0-9a-f]{6}$/i')
        ->and(designContrast($tokens[$foreground], $tokens[$background]))->toBeGreaterThanOrEqual(4.5);
})->with(['light', 'dark'])->with('text pairs');

it('allows literal colors only inside the token block', function () {
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());

    $outsideTokens = (string) preg_replace('/^:root(\.dark)?\s*\{.*?^\}/ms', '', $css);

    expect($outsideTokens)
        ->not->toMatch('/#[0-9a-f]{3,8}\b/i')
        ->not->toMatch('/\b(rgba?|hsla?|oklch|oklab|lab|lch|hwb|color)\(/i');

    $namedColors = 'white|black|red|green|blue|yellow|orange|purple|pink|gray|grey|silver|navy|maroon|olive'
        .'|lime|aqua|teal|fuchsia|cyan|magenta|brown|gold|indigo|violet|beige|ivory|khaki|coral|salmon|tomato'
        .'|crimson|orchid|plum|tan|wheat|snow|linen|azure|lavender|turquoise|chocolate';

    // Plain declarations only: `@apply` lines hold Tailwind utilities, which resolve to the palette.
    preg_match_all('/^\s*(?!@)[a-z-]+\s*:\s*([^;{]+);/mi', $outsideTokens, $declarations);

    foreach ($declarations[1] as $value) {
        $withoutVariables = (string) preg_replace('/var\([^)]*\)/', '', $value);

        expect($withoutVariables)->not->toMatch("/\\b({$namedColors})\\b/i");
    }
});

it('registers the fixed primary and gray scales with Filament', function () {
    // Panel::boot() registers the colors with FilamentColor; visiting a panel route triggers it.
    $this->get('/panel/login');

    $palettes = FilamentColor::getColors();

    expect($palettes['primary'])->toBe(array_map(Color::convertToOklch(...), AdminPanelProvider::PRIMARY_SCALE))
        ->and($palettes['gray'])->toBe(array_map(Color::convertToOklch(...), AdminPanelProvider::GRAY_SCALE));
});

it('keeps the token table anchors in the scales', function () {
    $light = designTokens('light');
    $dark = designTokens('dark');

    expect(strtolower(AdminPanelProvider::PRIMARY_SCALE[600]))->toBe($light['mt-primary'])
        ->and(strtolower(AdminPanelProvider::PRIMARY_SCALE[700]))->toBe($light['mt-primary-hover'])
        ->and(strtolower(AdminPanelProvider::PRIMARY_SCALE[400]))->toBe($dark['mt-primary'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[50]))->toBe($light['mt-table-head-bg'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[100]))->toBe($light['mt-row-divider'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[200]))->toBe($light['mt-border'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[600]))->toBe($light['mt-text-muted'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[800]))->toBe($dark['mt-border'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[900]))->toBe($dark['mt-panel-bg'])
        ->and(strtolower(AdminPanelProvider::GRAY_SCALE[950]))->toBe($dark['mt-page-bg']);
});
