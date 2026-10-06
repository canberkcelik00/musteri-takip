<?php

use App\Filament\Support\RecordActionsSection;
use App\Livewire\TopbarActions;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * The topbar's "Ödeme al" and "Yeni" (G-4.13), used by any test that used to reach "Ödeme al" or
 * "Elle borç" as a header action of a resource list.
 */
function topbarActions()
{
    return Livewire::test(TopbarActions::class);
}

/**
 * A rarer action inside the named section of a record's edit panel (G-4.17): the path of the
 * edit action followed by the nested one, in the normal or the danger row of the section.
 *
 * @return list<TestAction>
 */
function sectionAction(TestAction $panel, string $name, bool $danger = false): array
{
    return [
        $panel,
        TestAction::make($name)->schemaComponent($danger ? RecordActionsSection::DANGER_ACTIONS_KEY : RecordActionsSection::ACTIONS_KEY),
    ];
}

/**
 * Whether the Livewire test component offers the (nested) action: an action that is hidden, or
 * whose whole section is hidden, cannot be resolved at all.
 *
 * @param  list<TestAction>|TestAction  $path
 */
function actionOffered(mixed $component, array|TestAction $path): bool
{
    try {
        $component->assertActionVisible($path);
    } catch (ActionNotResolvableException|AssertionFailedError) {
        return false;
    }

    return true;
}

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
 * Colors a styled component rule may take: a token or one of these keywords, nothing else.
 */
const DESIGN_COLOR_VALUE = '/^(var\(--mt-[a-z0-9-]+\)|transparent|none|inherit|currentColor)$/';

/**
 * The text between the braces of every rule that starts with the given selector, nested rules
 * included.
 *
 * @return array<int, string>
 */
function designRuleBodies(string $selector): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', designThemeCss());
    $bodies = [];
    $offset = 0;

    while (preg_match('/^[ \t]*'.preg_quote($selector, '/').'\s*(?:,[^{]*)?\{/m', $css, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $position = $start;

        while ($depth > 0 && $position < strlen($css)) {
            $depth += match ($css[$position]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $position++;
        }

        $bodies[] = substr($css, $start, $position - $start - 1);
        $offset = $position;
    }

    return $bodies;
}

/**
 * Declarations of color properties in a rule body (`color`, `background`, `*-color`).
 *
 * @return array<int, array{string, string}>
 */
function designColorDeclarations(string $body): array
{
    preg_match_all('/^\s*(color|background|background-color|border-color|border-[a-z-]+-color|outline-color)\s*:\s*([^;]+);/m', $body, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => [$match[1], trim($match[2])], $matches);
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

/**
 * The text of the window the Livewire test component has mounted an action in (G-4B.7): Filament
 * renders an action's modal through a partial that the test's `html()` does not contain, so the
 * mounted action's schema is rendered directly.
 */
function mountedActionWindowText(mixed $component): string
{
    $schema = (fn () => $this->getMountedActionSchema())->call($component->instance());

    return trim((string) preg_replace('/\s+/', ' ', strip_tags($schema->toHtml())));
}
