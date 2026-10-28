<?php

use App\Actions\Account\CreateMcpToken;
use App\Enums\McpAccess;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

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

function mcpToken(?User $user = null, McpAccess $access = McpAccess::ReadOnly): string
{
    return app(CreateMcpToken::class)->handle($user ?? User::factory()->admin()->create(), 'Ofis', $access);
}

it('refuses a call without a token', function () {
    mcpCall(null)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');
});

it('refuses a wrong token', function () {
    mcpToken();

    mcpCall('1|not-the-token')->assertUnauthorized();
    mcpCall('not-a-token-at-all')->assertUnauthorized();
});

it('answers initialize and tools/list with a valid token', function () {
    $token = mcpToken();

    mcpCall($token)
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Müşteri Takip');

    mcpCall($token, 'tools/list')
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'today-summary')
        ->assertJsonCount(7, 'result.tools');
});

it('records when a token was last used', function () {
    $this->travelTo('2026-10-28 10:00');
    $token = mcpToken();

    mcpCall($token)->assertOk();

    expect(PersonalAccessToken::query()->sole()->last_used_at?->format('Y-m-d H:i'))->toBe('2026-10-28 10:00');
});

it('stops working after 90 days', function () {
    $this->travelTo('2026-10-28 10:00');
    $token = mcpToken();

    $this->travelTo('2027-01-26 09:59');
    mcpCall($token)->assertOk();

    app('auth')->forgetGuards();
    $this->travelTo('2027-01-26 10:01');
    mcpCall($token)->assertUnauthorized();
});

it('refuses a revoked token', function () {
    $token = mcpToken();

    PersonalAccessToken::query()->delete();

    mcpCall($token)->assertUnauthorized();
});

it('refuses the token of a deactivated user', function () {
    $user = User::factory()->admin()->create();
    $token = mcpToken($user);

    $user->forceFill(['deactivated_at' => now()])->save();

    mcpCall($token)->assertUnauthorized();
});

it('refuses a token made for something other than Claude access', function () {
    $token = User::factory()->admin()->create()->createToken('Başka', ['other'])->plainTextToken;

    mcpCall($token)->assertForbidden();
});

it('does not let the panel session in', function () {
    $this->actingAs(User::factory()->admin()->create());

    mcpCall(null)->assertUnauthorized();
});

it('allows 60 calls a minute for each token', function () {
    $token = mcpToken();
    $other = mcpToken();

    foreach (range(1, 60) as $call) {
        mcpCall($token, 'tools/list')->assertOk();
    }

    mcpCall($token, 'tools/list')->assertTooManyRequests();

    app('auth')->forgetGuards();
    mcpCall($other, 'tools/list')->assertOk();
});

it('keeps only the hash of the token', function () {
    $token = mcpToken();
    [, $plain] = explode('|', $token, 2);

    $stored = PersonalAccessToken::query()->sole()->getRawOriginal('token');

    expect($stored)->toBe(hash('sha256', $plain))->not->toContain($plain);
});

it('writes the calls to their own daily log', function () {
    expect(config('logging.channels.mcp'))
        ->driver->toBe('daily')
        ->path->toBe(storage_path('logs/mcp.log'));
});
