<?php

use App\Actions\Account\RevokeMcpTokens;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

it('refuses a call without a token and points to the sign-in', function () {
    mcpCall(null)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => 'mcp']).'"');
});

it('refuses a wrong token', function () {
    claudeConnects(User::factory()->admin()->create());

    mcpCall('not-a-token-at-all')->assertUnauthorized();
    mcpCall('eyJhbGciOiJSUzI1NiJ9.e30.bad')->assertUnauthorized();
});

it('answers initialize and tools/list with a token of an approved connection', function () {
    ['token' => $token] = claudeConnects(User::factory()->admin()->create());

    mcpCall($token)
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Müşteri Takip');

    mcpCall($token, 'tools/list')
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'today-summary')
        ->assertJsonCount(8, 'result.tools');
});

it('stops working when the access token has run out', function () {
    // The signed token carries its own expiry, read against the real clock, so an hour that is already over is issued.
    Passport::tokensExpireIn(CarbonInterval::minutes(5)->invert());

    try {
        ['token' => $token] = claudeConnects(User::factory()->admin()->create());

        mcpCall($token)->assertUnauthorized();
    } finally {
        Passport::tokensExpireIn(CarbonInterval::hour());
    }
});

it('refuses a revoked token', function () {
    $user = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($user);

    app(RevokeMcpTokens::class)->handle($user);

    mcpCall($token)->assertUnauthorized();
});

it('refuses the token of a deactivated user', function () {
    $user = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($user);

    $user->forceFill(['deactivated_at' => now()])->save();

    mcpCall($token)->assertUnauthorized();
});

it('refuses a token that does not carry the MCP scope', function () {
    ['token' => $token] = claudeConnects(User::factory()->admin()->create(), scope: '');

    mcpCall($token)->assertForbidden();
});

it('does not let the panel session in', function () {
    $this->actingAs(User::factory()->admin()->create());

    mcpCall(null)->assertUnauthorized();
});

it('allows 60 calls a minute for each connection of a user', function () {
    $user = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($user);
    ['token' => $other] = claudeConnects($user, name: 'Telefon');

    foreach (range(1, 60) as $call) {
        mcpCall($token, 'tools/list')->assertOk();
    }

    mcpCall($token, 'tools/list')->assertTooManyRequests();

    auth()->forgetGuards();
    mcpCall($other, 'tools/list')->assertOk();
});

it('never keeps the bearer token itself', function () {
    ['token' => $token, 'refresh' => $refresh] = claudeConnects(User::factory()->admin()->create());

    $stored = json_encode([
        DB::table('oauth_access_tokens')->get(),
        DB::table('oauth_refresh_tokens')->get(),
        DB::table('oauth_auth_codes')->get(),
    ]);

    expect($stored)->not->toContain($token)->not->toContain($refresh);
});

it('writes the calls to their own daily log', function () {
    expect(config('logging.channels.mcp'))
        ->driver->toBe('daily')
        ->path->toBe(storage_path('logs/mcp.log'));
});
