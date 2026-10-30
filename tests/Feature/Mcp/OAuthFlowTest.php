<?php

use App\Actions\Account\RevokeMcpTokens;
use App\Actions\Account\SetMcpConnectionAccess;
use App\Enums\McpAccess;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The PKCE challenge (S256) of a verifier, the way a client sends it.
 */
function pkceChallenge(?string $verifier = null): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier ?? Str::random(64), true)), '+/', '-_'), '=');
}

it('tells a client where the sign-in is', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('authorization_servers.0', url('/'))
        ->assertJsonPath('scopes_supported', ['mcp:use']);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

it('lets a client register with the redirect addresses of Claude only', function (string $redirect, int $status) {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])->assertStatus($status);
})->with([
    'Claude on the web and desktop' => ['https://claude.ai/api/mcp/auth_callback', 201],
    'Claude on another Anthropic address' => ['https://claude.com/api/mcp/auth_callback', 201],
    'Claude Code on this computer' => ['http://localhost:54545/callback', 201],
    'a stranger' => ['https://evil.example/callback', 400],
    'a look-alike of Claude' => ['https://claude.ai.evil.example/callback', 400],
    'a custom scheme' => ['claude://callback', 400],
]);

it('limits the registrations of one address to ten a minute', function () {
    foreach (range(1, 10) as $time) {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertCreated();
    }

    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertTooManyRequests();
});

it('sends a guest who comes for the consent screen to the sign-in', function () {
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->json('client_id');

    $this->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'scope' => 'mcp:use',
        'code_challenge' => pkceChallenge(),
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('app.login'));
});

it('shows the consent screen to a signed-in user and gives nothing before they approve', function () {
    $user = User::factory()->admin()->create(['email' => 'sahip@example.com']);
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude Masaüstü', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->json('client_id');

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => pkceChallenge(),
        'code_challenge_method' => 'S256',
    ]))
        ->assertOk()
        ->assertSee('"Claude Masaüstü" bağlanmak istiyor')
        ->assertSee('sahip@example.com')
        ->assertSee('claude.ai')
        ->assertSee(__('mcp.authorize.read_only'))
        ->assertSee(__('mcp.authorize.no_delete'))
        ->assertSee(__('mcp.authorize.approve'))
        ->assertSee(__('mcp.authorize.deny'));

    expect(McpConnection::query()->count())->toBe(0);
});

it('gives no code when the user denies', function () {
    $user = User::factory()->admin()->create();
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->json('client_id');

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => pkceChallenge(),
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    $location = $this->delete('/oauth/authorize', ['state' => 'abc', 'client_id' => $clientId, 'auth_token' => session('authToken')])
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toContain('error=access_denied')->not->toContain('code=');
    expect(McpConnection::query()->count())->toBe(0);
});

it('starts every approved connection read only and keeps one row for it', function () {
    $user = User::factory()->admin()->create();

    ['token' => $token, 'refresh' => $refresh, 'client_id' => $clientId] = claudeConnects($user);

    $connection = McpConnection::query()->sole();

    expect($connection->user_id)->toBe($user->id)
        ->and($connection->oauth_client_id)->toBe($clientId)
        ->and($connection->access)->toBe(McpAccess::ReadOnly);

    $names = fn () => collect(mcpCall($token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name');

    expect($names())->toHaveCount(8)->not->toContain('create-task');

    // Renewing the token makes a new access token for the same connection, not a second connection.
    auth()->forgetGuards();
    $renewed = $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId])
        ->assertOk()
        ->json('access_token');

    expect(McpConnection::query()->count())->toBe(1);
    mcpCall($renewed)->assertOk();
});

it('shows the write tools the moment the user lets the connection write', function () {
    $user = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($user);

    app(SetMcpConnectionAccess::class)->handle($user, McpConnection::query()->sole()->id, McpAccess::ReadWrite);

    $names = collect(mcpCall($token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name');

    expect($names)->toHaveCount(11)->toContain('create-task', 'complete-task', 'record-payment');

    app(SetMcpConnectionAccess::class)->handle($user, McpConnection::query()->sole()->id, McpAccess::ReadOnly);
    auth()->forgetGuards();

    expect(collect(mcpCall($token, 'tools/list')->json('result.tools'))->pluck('name'))->toHaveCount(8);
});

it('does not let a connection of one user write because another user\'s connection may', function () {
    $writer = User::factory()->admin()->create();
    $reader = User::factory()->admin()->create();
    claudeConnects($writer, McpAccess::ReadWrite);
    ['token' => $readerToken] = claudeConnects($reader);

    expect(collect(mcpCall($readerToken, 'tools/list')->json('result.tools'))->pluck('name'))->toHaveCount(8);
});

it('refuses the refresh token once the connection was cut', function () {
    $user = User::factory()->admin()->create();
    ['refresh' => $refresh, 'client_id' => $clientId] = claudeConnects($user);

    app(RevokeMcpTokens::class)->handle($user);

    $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_grant');
});

it('refuses a code traded without the right PKCE verifier', function () {
    $user = User::factory()->admin()->create();
    $redirect = 'https://claude.ai/api/mcp/auth_callback';
    $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])->json('client_id');
    $verifier = Str::random(64);

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'response_type' => 'code',
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => pkceChallenge($verifier),
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    parse_str((string) parse_url($this->post('/oauth/authorize', ['state' => 'abc', 'client_id' => $clientId, 'auth_token' => session('authToken')])->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'code_verifier' => Str::random(64),
        'code' => $query['code'],
    ])->assertStatus(400);

    expect(McpConnection::query()->count())->toBe(0);
});
