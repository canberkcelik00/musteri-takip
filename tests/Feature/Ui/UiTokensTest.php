<?php

/*
 * The glass interface's design tokens (G-10.1, docs/UI.md §6): text stays readable on every glass fill as it
 * composites over the lightest and the darkest background blob, literal colors live only in the token blocks, and
 * `backdrop-filter` is set in `.ui-glass` alone.
 */

const UI_BLOBS = ['mt-bg-base', 'mt-bg-a', 'mt-bg-b', 'mt-bg-c', 'mt-bg-d'];

const UI_GLASS_FILLS = ['mt-glass-bg', 'mt-glass-bg-strong', 'mt-glass-bg-menu'];

/**
 * Contrast of a text token on the surface made by painting the given layers (tokens, bottom to top) over a blob.
 *
 * @param  list<string>  $layers
 */
function uiGlassContrast(array $tokens, string $text, string $blob, array $layers): float
{
    $surface = uiColor($tokens[$blob]);

    foreach ($layers as $layer) {
        $surface = uiOver(uiColor($tokens[$layer]), $surface);
    }

    return designContrast(uiHex(uiOver(uiColor($tokens[$text]), $surface)), uiHex($surface));
}

it('keeps text readable on every glass surface over every background blob', function (string $theme) {
    $tokens = uiTokens($theme);
    $failures = [];

    $cases = [
        // [text tokens, extra layers over the glass fill, minimum]
        [['mt-text', 'mt-text-muted', 'mt-text-tertiary', 'mt-link', 'mt-whatsapp', 'mt-danger-text'], [], 4.5],
        [['mt-text', 'mt-text-muted'], ['mt-row-hover'], 4.5],
        [['mt-text', 'mt-text-muted'], ['mt-item-hover'], 4.5],
        [['mt-text', 'mt-text-muted', 'mt-link'], ['mt-item-active'], 4.5],
        [['mt-text'], ['mt-button-bg'], 4.5],
        [['mt-danger-text'], ['mt-danger-bg'], 4.5],
        [['mt-danger-text'], ['mt-danger-hover-bg'], 4.5],
        [['mt-text', 'mt-text-tertiary'], ['mt-input-bg'], 4.5],
    ];

    foreach ($cases as [$texts, $extra, $minimum]) {
        foreach ($texts as $text) {
            foreach (UI_BLOBS as $blob) {
                foreach (UI_GLASS_FILLS as $fill) {
                    $ratio = uiGlassContrast($tokens, $text, $blob, [$fill, ...$extra]);

                    if ($ratio < $minimum) {
                        $failures[] = sprintf('%s: %s on %s over %s%s is %.2f', $theme, $text, $fill, $blob, $extra === [] ? '' : ' + '.implode('+', $extra), $ratio);
                    }
                }
            }
        }
    }

    expect($failures)->toBe([]);
})->with(['light', 'dark']);

it('keeps text readable on the flat surface used without glass', function (string $theme) {
    $tokens = uiTokens($theme);

    foreach (['mt-text', 'mt-text-muted', 'mt-text-tertiary', 'mt-link', 'mt-whatsapp', 'mt-danger-text'] as $text) {
        expect(designContrast($tokens[$text], $tokens['mt-surface']))->toBeGreaterThanOrEqual(4.5, "{$theme}: {$text} on mt-surface");
    }
})->with(['light', 'dark']);

dataset('ui opaque pairs', [
    'primary button' => ['mt-on-primary', 'mt-primary', 4.5],
    'tooltip' => ['mt-tooltip-text', 'mt-tooltip-bg', 4.5],
    'red badge' => ['mt-badge-red-text', 'mt-badge-red-bg', 4.5],
    'amber badge' => ['mt-badge-amber-text', 'mt-badge-amber-bg', 4.5],
    'green badge' => ['mt-badge-green-text', 'mt-badge-green-bg', 4.5],
    'gray badge' => ['mt-badge-gray-text', 'mt-badge-gray-bg', 4.5],
    'selected segment' => ['mt-text', 'mt-segment-active-bg', 4.5],
    'avatar 1' => ['mt-on-nav', 'mt-avatar-1', 4.5],
    'avatar 2' => ['mt-on-nav', 'mt-avatar-2', 4.5],
    'avatar 3' => ['mt-on-nav', 'mt-avatar-3', 4.5],
    'avatar 4' => ['mt-on-nav', 'mt-avatar-4', 4.5],
    'avatar 5' => ['mt-on-nav', 'mt-avatar-5', 4.5],
    'avatar 6' => ['mt-on-nav', 'mt-avatar-6', 4.5],
    'avatar 7' => ['mt-on-nav', 'mt-avatar-7', 4.5],
    'avatar 8' => ['mt-on-nav', 'mt-avatar-8', 4.5],
    // The menu squares carry an icon, a graphic: 3:1 (WCAG 1.4.11).
    'menu blue' => ['mt-on-nav', 'mt-nav-blue', 3.0],
    'menu indigo' => ['mt-on-nav', 'mt-nav-indigo', 3.0],
    'menu orange' => ['mt-on-nav', 'mt-nav-orange', 3.0],
    'menu purple' => ['mt-on-nav', 'mt-nav-purple', 3.0],
    'menu green' => ['mt-on-nav', 'mt-nav-green', 3.0],
    'menu teal' => ['mt-on-nav', 'mt-nav-teal', 3.0],
    'menu gray' => ['mt-on-nav', 'mt-nav-gray', 3.0],
]);

it('keeps opaque color pairs readable', function (string $foreground, string $background, float $minimum, string $theme) {
    $tokens = uiTokens($theme);

    expect(designContrast($tokens[$foreground], $tokens[$background]))->toBeGreaterThanOrEqual($minimum);
})->with('ui opaque pairs')->with(['light', 'dark']);

it('writes literal colors only in the token blocks', function () {
    foreach (uiStylesheets() as $name => $css) {
        if ($name === 'tokens') {
            continue;
        }

        expect(preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(|\boklch\(|\b(?:white|black)\b(?!-)/', $css, $matches))
            ->toBe(0, "ui/{$name}.css has a literal color: ".implode(', ', $matches[0]));
    }
});

it('defines every token a rule uses, and the dark block only overrides', function () {
    $light = uiTokens('light');

    foreach (uiStylesheets() as $name => $css) {
        preg_match_all('/var\(--(mt-[a-z0-9-]+)\)/', $css, $matches);

        foreach (array_unique($matches[1]) as $token) {
            expect(array_key_exists($token, $light))->toBeTrue("ui/{$name}.css uses the undefined token --{$token}.");
        }
    }

    preg_match_all('/--(mt-[a-z0-9-]+)\s*:/', uiTokenBlock(':root.dark'), $dark);

    foreach ($dark[1] as $token) {
        expect(array_key_exists($token, $light))->toBeTrue("The dark block overrides --{$token}, which the light block does not define.");
    }
});

it('sets backdrop-filter in the .ui-glass rule only', function () {
    $found = false;

    foreach (uiStylesheets() as $name => $css) {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as [, $selector, $declarations]) {
            if (preg_match('/backdrop-filter\s*:\s*([^;]+);/', $declarations, $match) !== 1) {
                continue;
            }

            if (trim($match[1]) === 'none') {
                continue;
            }

            $found = true;
            expect(trim($selector))->toBe('.ui-glass', "ui/{$name}.css blurs in [".trim($selector).'].');
        }
    }

    expect($found)->toBeTrue();
});

it('falls back to flat surfaces without glass and with reduced transparency', function () {
    $base = uiStylesheets()['base'];

    expect($base)
        ->toContain('html.no-glass .ui-glass')
        ->toContain('@media (prefers-reduced-transparency: reduce)');
});
