<?php

use App\Actions\Deploy\ArtisanRunner;
use App\Actions\Deploy\DeployRelease;
use App\Filament\Support\RecordActionsSection;
use App\Livewire\TopbarActions;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\CustomerService;
use BladeUI\Icons\IconsManifest;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * The topbar's "Ödeme al" and "Yeni müşteri" (G-4.13), used by any test that used to reach "Ödeme al"
 * as a header action of a resource list.
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

/**
 * The stylesheets of the glass interface (Faz 10), in the order ui.css imports them.
 *
 * @return array<string, string> file name => CSS without comments
 */
function uiStylesheets(): array
{
    $sheets = [];

    foreach (['tokens', 'base', 'controls', 'tables'] as $name) {
        $sheets[$name] = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path("css/ui/{$name}.css")));
    }

    return $sheets;
}

/**
 * The body of the token rule with the given selector (`:root` or `:root.dark`) in ui/tokens.css.
 */
function uiTokenBlock(string $selector): string
{
    $found = preg_match('/^'.preg_quote($selector, '/').'\s*\{(.*?)^\}/ms', uiStylesheets()['tokens'], $matches);

    expect($found)->toBe(1, "Token block {$selector} is missing from ui/tokens.css.");

    return $matches[1];
}

/**
 * The tokens of a theme: the dark theme is the light block overridden by the dark one.
 *
 * @return array<string, string> name (without the leading dashes) => value
 */
function uiTokens(string $theme): array
{
    $tokens = [];

    foreach ($theme === 'dark' ? [':root', ':root.dark'] : [':root'] as $selector) {
        preg_match_all('/--(mt-[a-z0-9-]+)\s*:\s*([^;]+);/', uiTokenBlock($selector), $matches, PREG_SET_ORDER);

        foreach ($matches as [, $name, $value]) {
            $tokens[$name] = trim($value);
        }
    }

    return $tokens;
}

/**
 * A `#rrggbb` or `rgba(r, g, b, a)` token value as [r, g, b, alpha].
 *
 * @return array{float, float, float, float}
 */
function uiColor(string $value): array
{
    if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $value, $m) === 1) {
        return [(float) hexdec($m[1]), (float) hexdec($m[2]), (float) hexdec($m[3]), 1.0];
    }

    $found = preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m);

    expect($found)->toBe(1, "Cannot read the color [{$value}].");

    return [(float) $m[1], (float) $m[2], (float) $m[3], (float) ($m[4] ?? 1)];
}

/**
 * Paint `$top` (any alpha) over the opaque `$bottom`; the result is an opaque `#rrggbb`.
 *
 * @param  array{float, float, float, float}  $top
 * @param  array{float, float, float, float}  $bottom
 * @return array{float, float, float, float}
 */
function uiOver(array $top, array $bottom): array
{
    $mix = fn (float $above, float $below): float => $above * $top[3] + $below * (1 - $top[3]);

    return [$mix($top[0], $bottom[0]), $mix($top[1], $bottom[1]), $mix($top[2], $bottom[2]), 1.0];
}

/**
 * @param  array{float, float, float, float}  $color
 */
function uiHex(array $color): string
{
    return sprintf('#%02x%02x%02x', (int) round($color[0]), (int) round($color[1]), (int) round($color[2]));
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

const DEPLOY_TEST_KEY = 'deploy-test-key-0123456789abcdef-xyz';

const DEPLOY_STEPS = ['optimize:clear', 'filament:clear-cached-components', 'migrate', 'optimize', 'filament:cache-components'];

/**
 * The temporary directory the running deploy test created, or null. Kept here instead of being
 * derived from config on cleanup: an empty config value would resolve to the project root.
 */
function deployTestDirectory(?string $set = null, bool $forget = false): ?string
{
    static $directory = null;

    if ($forget) {
        $directory = null;
    }

    if ($set !== null) {
        $directory = $set;
    }

    return $directory;
}

/**
 * Point the deploy at a temporary directory, set the deploy key and replace the Artisan
 * runner with one that records the commands instead of running them (a real `optimize`
 * would write cache files into the test environment).
 *
 * @return object{calls: list<string>}
 */
function prepareDeploy(?string $release = 'release-1', ?string $failingCommand = null): object
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'deploy-test-'.uniqid();
    mkdir($directory);
    deployTestDirectory($directory);

    config()->set('app.deploy', [
        'key' => DEPLOY_TEST_KEY,
        'git_directory' => $directory.'/.git',
        'state_file' => $directory.'/deployed-release',
    ]);

    if ($release !== null) {
        pullDeployRelease($release);
    }

    app()->instance(IconsManifest::class, new IconsManifest(new Filesystem, $directory.'/blade-icons.php'));

    return swapArtisanRunner($failingCommand);
}

/**
 * Simulate a pull in the temporary repository `prepareDeploy()` created: main moves to the
 * given commit id, the way cPanel Git leaves `.git` after "Update from Remote".
 */
function pullDeployRelease(string $release): void
{
    $git = deployTestDirectory().'/.git';

    if (! is_dir($git.'/refs/heads')) {
        mkdir($git.'/refs/heads', recursive: true);
        file_put_contents($git.'/HEAD', "ref: refs/heads/main\n");
    }

    file_put_contents($git.'/refs/heads/main', $release);
}

/**
 * @return object{calls: list<string>, parameters: list<array<string, mixed>>}
 */
function swapArtisanRunner(?string $failingCommand = null): object
{
    $runner = new class($failingCommand) extends ArtisanRunner
    {
        /** @var list<string> */
        public array $calls = [];

        /** @var list<array<string, mixed>> */
        public array $parameters = [];

        public function __construct(private readonly ?string $failingCommand) {}

        public function run(string $command, array $parameters = []): int
        {
            $this->calls[] = $command;
            $this->parameters[] = $parameters;

            return $command === $this->failingCommand ? 1 : 0;
        }
    };

    app()->instance(ArtisanRunner::class, $runner);

    return $runner;
}

function deployedRelease(): ?string
{
    $directory = deployTestDirectory();

    return $directory !== null && is_file($directory.'/deployed-release')
        ? (string) file_get_contents($directory.'/deployed-release')
        : null;
}

/**
 * Remove the directory `prepareDeploy()` created, and nothing else: it must be a
 * `deploy-test-*` directory directly inside the system temp directory.
 */
function cleanUpDeploy(): void
{
    $directory = deployTestDirectory();
    deployTestDirectory(forget: true);

    if ($directory === null) {
        return;
    }

    $real = realpath($directory);
    $temp = realpath(sys_get_temp_dir());

    if ($real === false || $temp === false || dirname($real) !== $temp || ! str_starts_with(basename($real), 'deploy-test-')) {
        return;
    }

    File::deleteDirectory($real);
}

/**
 * Decide whether a pulled release is waiting to be deployed, for the scheduler health check
 * (G-5.6); the real check reads the test environment's own git checkout.
 */
function fakePendingRelease(bool $pending): void
{
    test()->mock(DeployRelease::class)
        ->shouldReceive('hasPendingRelease')
        ->andReturn($pending);
}

/**
 * A renewal charge for the period 01.03.2027 – 01.03.2028 of a service that ends on 01.03.2027,
 * whose customer has a primary contact (today is 10.02.2027, inside the renewal window).
 *
 * @param  array<string, mixed>  $charge
 * @param  array<string, mixed>  $contact
 * @param  array<string, mixed>  $service
 */
function renewalChargeToRemind(array $charge = [], array $contact = [], array $service = []): Charge
{
    $customerService = CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$service,
    ]);

    Contact::factory()->primary()->create([
        'customer_id' => $customerService->customer_id,
        'name' => 'Ayşe Yılmaz',
        'email' => 'ayse@example.com',
        ...$contact,
    ]);

    return Charge::factory()->forCustomerService($customerService)->renewal()->create([
        'due_date' => '2027-03-01',
        'period_start' => '2027-03-01',
        'period_end' => '2028-03-01',
        ...$charge,
    ]);
}
