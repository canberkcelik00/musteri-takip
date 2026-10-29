<?php

use App\Actions\Deploy\ArtisanRunner;
use App\Actions\Deploy\DeployRelease;
use App\Enums\McpAccess;
use App\Mcp\Servers\MusteriTakipServer;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\CustomerService;
use App\Models\McpConnection;
use App\Models\User;
use BladeUI\Icons\IconsManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => passportTestKeys())
    ->in('Feature');

/**
 * An RSA key pair for signing the OAuth access tokens of the Claude access (U-43), made once per run and handed to
 * Passport through its config, so no key file has to exist.
 */
function passportTestKeys(): void
{
    static $keys = null;

    if ($keys === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $keys = [$private, openssl_pkey_get_details($resource)['key']];
    }

    config()->set('passport.private_key', $keys[0]);
    config()->set('passport.public_key', $keys[1]);
}

/**
 * The Claude server (Faz 18) acting as the user, through a Claude connection (an OAuth client with a live token of the
 * MCP scope) that has the given access (as the endpoint does).
 *
 * @param  list<string>  $scopes
 */
function mcpAs(User $user, McpAccess $access = McpAccess::ReadOnly, array $scopes = [Registrar::OAUTH_SCOPE]): PendingTestResponse
{
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], confidential: false);

    $token = Passport::token()->newQuery()->create([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'scopes' => $scopes,
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    McpConnection::query()->updateOrCreate(
        ['user_id' => $user->id, 'oauth_client_id' => $client->getKey()],
        ['access' => $access],
    );

    return MusteriTakipServer::actingAs($user->withAccessToken(new AccessToken([
        'oauth_client_id' => $client->getKey(),
        'oauth_access_token_id' => $token->id,
        'oauth_user_id' => $user->id,
        'oauth_scopes' => $scopes,
    ])));
}

/**
 * Send one JSON-RPC message to the Claude access endpoint, with the token as a bearer header when given.
 *
 * @param  array<string, mixed>  $params
 */
function mcpCall(?string $token, string $method = 'initialize', array $params = []): TestResponse
{
    if ($method === 'initialize' && $params === []) {
        $params = ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1.0']];
    }

    $test = test();

    if ($token !== null) {
        $test->withToken($token);
    }

    return $test->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
}

/**
 * What a Claude app does to get in, over HTTP, the way Claude does (U-43): register itself, send the signed-in user to
 * the consent screen, approve, and trade the code (with PKCE) for tokens. Afterwards nobody is signed in to the panel,
 * so only the returned tokens open the endpoint. The new connection starts read only unless `$access` says otherwise.
 *
 * @return array{token: string, refresh: string, client_id: string}
 */
function claudeConnects(User $user, McpAccess $access = McpAccess::ReadOnly, string $name = 'Claude', string $scope = Registrar::OAUTH_SCOPE): array
{
    $test = test();
    $redirect = 'https://claude.ai/api/mcp/auth_callback';

    // A route keeps its controller, and Passport's holds the session guard it got on the first call; a real request
    // starts a fresh application, so drop that to let the next user sign in.
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $route->flushController();
    }

    $clientId = $test->postJson('/oauth/register', ['client_name' => $name, 'redirect_uris' => [$redirect]])
        ->assertCreated()
        ->json('client_id');

    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $test->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'scope' => $scope,
        'state' => 'abc',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    $location = $test->post('/oauth/authorize', ['state' => 'abc', 'client_id' => $clientId, 'auth_token' => session('authToken')])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $tokens = $test->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json();

    McpConnection::query()->where('user_id', $user->id)->where('oauth_client_id', $clientId)->update(['access' => $access]);

    auth()->forgetGuards();
    $test->flushSession();

    return ['token' => $tokens['access_token'], 'refresh' => $tokens['refresh_token'], 'client_id' => $clientId];
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

const DEPLOY_TEST_KEY = 'deploy-test-key-0123456789abcdef-xyz';

const DEPLOY_STEPS = ['optimize:clear', 'migrate', 'optimize'];

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

/**
 * The user an Action is handed as `$actor` (T-159).
 */
function actor(): User
{
    return User::factory()->create();
}
