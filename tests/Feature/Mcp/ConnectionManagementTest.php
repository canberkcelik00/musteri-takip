<?php

use App\Actions\Account\DisconnectMcpClient;
use App\Actions\Account\ListMcpConnections;
use App\Actions\Account\SetMcpConnectionAccess;
use App\Actions\Users\DeactivateUser;
use App\Enums\McpAccess;
use App\Livewire\Pages\Auth\Profile;
use App\Livewire\Windows\Confirm;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Livewire\Livewire;

it('has no Sanctum token table any more', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse()
        ->and(Schema::hasTable('mcp_connections'))->toBeTrue();
});

it('lists the user\'s own live connections with their access', function () {
    $user = User::factory()->admin()->create();
    claudeConnects($user, name: 'Claude Masaüstü');
    claudeConnects($user, McpAccess::ReadWrite, name: 'Claude Telefon');
    claudeConnects(User::factory()->admin()->create(), name: 'Başkasının');

    $rows = app(ListMcpConnections::class)->handle($user);

    expect($rows->map(fn (array $row) => [$row['connection']->client->name, $row['connection']->access])->all())
        ->toBe([['Claude Telefon', McpAccess::ReadWrite], ['Claude Masaüstü', McpAccess::ReadOnly]]);
});

it('does not list a connection whose tokens are all revoked or ran out', function () {
    $user = User::factory()->admin()->create();
    claudeConnects($user, name: 'Eski');
    claudeConnects($user, name: 'Süresi dolmuş');
    claudeConnects($user, name: 'Canlı');

    $connections = McpConnection::query()->with('client')->get()->keyBy(fn (McpConnection $connection) => $connection->client->name);

    Passport::token()->newQuery()->where('client_id', $connections['Eski']->oauth_client_id)->update(['revoked' => true]);
    Passport::token()->newQuery()->where('client_id', $connections['Süresi dolmuş']->oauth_client_id)->update(['expires_at' => now()->subMinute()]);
    Passport::refreshToken()->newQuery()->whereIn('access_token_id', Passport::token()->newQuery()->where('client_id', $connections['Süresi dolmuş']->oauth_client_id)->select('id'))->update(['expires_at' => now()->subMinute()]);

    expect(app(ListMcpConnections::class)->handle($user)->map(fn (array $row) => $row['connection']->client->name)->all())->toBe(['Canlı']);
});

it('keeps listing a connection whose access token ran out but whose refresh token lives', function () {
    $user = User::factory()->admin()->create();
    claudeConnects($user, name: 'Claude');

    Passport::token()->newQuery()->update(['expires_at' => now()->subMinute()]);

    expect(app(ListMcpConnections::class)->handle($user))->toHaveCount(1);
});

it('shows the connections, the address and the command on the profile', function () {
    $user = User::factory()->admin()->create();
    claudeConnects($user, name: 'Claude Masaüstü');
    claudeConnects(User::factory()->admin()->create(), name: 'Başkasının');
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->assertSee(route('mcp'))
        ->assertSee('claude mcp add --scope user --transport http musteri-takip '.route('mcp'))
        ->assertDontSee('Authorization: Bearer')
        ->assertSee('Claude Masaüstü')
        ->assertDontSee('Başkasının')
        ->assertDontSee(__('mcp.none'));
});

it('says so when nothing is connected', function () {
    $this->actingAs(User::factory()->developer()->create());

    Livewire::test(Profile::class)->assertSee(__('mcp.none'));
});

it('lets the user switch write access of a connection on and off', function () {
    $user = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($user);
    $connection = McpConnection::query()->sole();
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->assertSet('write', [$connection->id => false])
        ->set('write.'.$connection->id, true)
        ->assertDispatched('notify', message: __('mcp.access_saved'));

    expect($connection->refresh()->access)->toBe(McpAccess::ReadWrite);

    Livewire::test(Profile::class)
        ->assertSet('write', [$connection->id => true])
        ->set('write.'.$connection->id, false);

    expect($connection->refresh()->access)->toBe(McpAccess::ReadOnly);
});

it('does not let a user change the access of someone else\'s connection', function () {
    $owner = User::factory()->admin()->create();
    claudeConnects($owner);
    $connection = McpConnection::query()->sole();
    $other = User::factory()->admin()->create();

    expect(fn () => app(SetMcpConnectionAccess::class)->handle($other, $connection->id, McpAccess::ReadWrite))->toThrow(ModelNotFoundException::class);

    $this->actingAs($other);
    Livewire::test(Profile::class)->set('write.'.$connection->id, true)->assertNotFound();

    expect($connection->refresh()->access)->toBe(McpAccess::ReadOnly);
});

it('cuts a connection through the confirm window and the app is out at once', function () {
    $user = User::factory()->developer()->create();
    ['token' => $token, 'refresh' => $refresh, 'client_id' => $clientId] = claudeConnects($user, name: 'Claude Masaüstü');
    $connection = McpConnection::query()->sole();
    $this->actingAs($user);

    Livewire::test(Confirm::class, ['action' => 'disconnect-mcp', 'id' => $connection->id])
        ->assertSee('"Claude Masaüstü" bağlantısı kesilsin mi?')
        ->assertSee(__('mcp.disconnect_description'))
        ->call('confirm')
        ->assertDispatched('mcp-connections-changed')
        ->assertDispatched('notify', message: __('mcp.disconnected'));

    expect(McpConnection::query()->count())->toBe(0);

    auth()->forgetGuards();
    $this->flushSession();
    mcpCall($token)->assertUnauthorized();

    $this->postJson('/oauth/token', ['grant_type' => 'refresh_token', 'refresh_token' => $refresh, 'client_id' => $clientId])
        ->assertStatus(400);
});

it('cuts only the chosen connection', function () {
    $user = User::factory()->admin()->create();
    ['token' => $first] = claudeConnects($user, name: 'Claude Masaüstü');
    ['token' => $second] = claudeConnects($user, name: 'Claude Telefon');

    app(DisconnectMcpClient::class)->handle($user, McpConnection::query()->whereHas('client', fn ($query) => $query->where('name', 'Claude Masaüstü'))->sole()->id);

    mcpCall($first)->assertUnauthorized();
    auth()->forgetGuards();
    mcpCall($second)->assertOk();
});

it('does not let a user cut someone else\'s connection through the confirm window', function () {
    $owner = User::factory()->admin()->create();
    ['token' => $token] = claudeConnects($owner, name: 'Claude Masaüstü');
    $connection = McpConnection::query()->sole();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Confirm::class, ['action' => 'disconnect-mcp', 'id' => $connection->id])
        ->assertDontSee('Claude Masaüstü')
        ->call('confirm')
        ->assertNotFound();

    expect(McpConnection::query()->count())->toBe(1);
    auth()->forgetGuards();
    $this->flushSession();
    mcpCall($token)->assertOk();
});

it('cuts the connections of a deactivated user and no one else\'s', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->developer()->create();
    ['token' => $userToken] = claudeConnects($user);
    ['token' => $adminToken] = claudeConnects($admin);

    app(DeactivateUser::class)->handle($admin, $user);

    expect(McpConnection::query()->pluck('user_id')->all())->toBe([$admin->id])
        ->and(Passport::token()->newQuery()->where('user_id', $user->id)->where('revoked', false)->count())->toBe(0);

    mcpCall($userToken)->assertUnauthorized();
    auth()->forgetGuards();
    mcpCall($adminToken)->assertOk();
});
