<?php

use App\Actions\Account\CreateMcpToken;
use App\Actions\Account\RevokeMcpToken;
use App\Actions\Users\DeactivateUser;
use App\Enums\McpAccess;
use App\Livewire\Pages\Auth\Profile;
use App\Livewire\Windows\Confirm;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

it('creates a read-only or a read and write token that ends after 90 days', function (McpAccess $access, array $abilities) {
    $this->travelTo('2026-10-28 10:00');
    $user = User::factory()->admin()->create();

    $plain = app(CreateMcpToken::class)->handle($user, 'Ofis', $access);

    $token = PersonalAccessToken::query()->sole();

    expect($plain)->toStartWith($token->id.'|')
        ->and($token->tokenable_id)->toBe($user->id)
        ->and($token->name)->toBe('Ofis')
        ->and($token->abilities)->toBe($abilities)
        ->and($token->expires_at?->format('Y-m-d H:i'))->toBe('2027-01-26 10:00');
})->with([
    'read only' => [McpAccess::ReadOnly, ['mcp:read']],
    'read and write' => [McpAccess::ReadWrite, ['mcp:read', 'mcp:write']],
]);

it('revokes only the user\'s own token', function () {
    $owner = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();
    app(CreateMcpToken::class)->handle($owner, 'Ofis', McpAccess::ReadOnly);
    $token = PersonalAccessToken::query()->sole();

    expect(fn () => app(RevokeMcpToken::class)->handle($other, $token->id))->toThrow(ModelNotFoundException::class);
    expect(PersonalAccessToken::query()->count())->toBe(1);

    app(RevokeMcpToken::class)->handle($owner, $token->id);
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('deletes the tokens of a deactivated user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->developer()->create();
    app(CreateMcpToken::class)->handle($user, 'Ofis', McpAccess::ReadOnly);
    app(CreateMcpToken::class)->handle($admin, 'Ev', McpAccess::ReadOnly);

    app(DeactivateUser::class)->handle($admin, $user);

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Ev']);
});

it('shows a new token and its command once on the profile', function () {
    $user = User::factory()->developer()->create();
    $this->actingAs($user);

    $component = Livewire::test(Profile::class)
        ->set('token_name', 'Ofis bilgisayarı')
        ->set('token_access', McpAccess::ReadWrite->value)
        ->call('createToken')
        ->assertHasNoErrors();

    $plain = $component->get('new_token');

    expect($plain)->toBeString();
    $component
        ->assertSee($plain)
        ->assertSee('claude mcp add --scope user --transport http musteri-takip '.route('mcp').' --header &quot;Authorization: Bearer '.$plain.'&quot;', false)
        ->assertSee(__('mcp.shown_once'))
        ->assertSet('token_name', '')
        ->assertSet('token_access', McpAccess::ReadOnly->value);

    expect(PersonalAccessToken::query()->sole()->abilities)->toBe(['mcp:read', 'mcp:write']);

    Livewire::test(Profile::class)
        ->assertDontSee($plain)
        ->assertDontSee(__('mcp.shown_once'))
        ->assertSee('Ofis bilgisayarı');
});

it('lists the tokens with access, last use and end date', function () {
    $this->travelTo('2026-10-28 10:00');
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    app(CreateMcpToken::class)->handle($user, 'Ofis', McpAccess::ReadOnly);
    app(CreateMcpToken::class)->handle($user, 'Ev', McpAccess::ReadWrite);
    PersonalAccessToken::query()->where('name', 'Ev')->update(['last_used_at' => '2026-10-28 09:30']);
    app(CreateMcpToken::class)->handle(User::factory()->admin()->create(), 'Başkasının', McpAccess::ReadOnly);

    Livewire::test(Profile::class)
        ->assertSeeInOrder(['Ev', 'Okuma ve yazma', '28.10.2026 09:30', '26.01.2027'])
        ->assertSeeInOrder(['Ofis', 'Yalnız okuma', 'Kullanılmadı', '26.01.2027'])
        ->assertDontSee('Başkasının')
        ->assertDontSee(__('mcp.expired'));

    $this->travelTo('2027-02-01 10:00');

    Livewire::test(Profile::class)->assertSee(__('mcp.expired'));
});

it('refuses a token without a name or with an unknown access', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Profile::class)
        ->set('token_name', '')
        ->set('token_access', 'everything')
        ->call('createToken')
        ->assertHasErrors(['token_name' => 'required', 'token_access'])
        ->assertSet('new_token', null);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes a token through the confirm window and refreshes the list', function () {
    $user = User::factory()->developer()->create();
    $this->actingAs($user);
    app(CreateMcpToken::class)->handle($user, 'Ofis', McpAccess::ReadOnly);
    $token = PersonalAccessToken::query()->sole();

    Livewire::test(Confirm::class, ['action' => 'revoke-mcp-token', 'id' => $token->id])
        ->assertSee('"Ofis" token\'ı iptal edilsin mi?')
        ->assertSee(__('mcp.revoke_description'))
        ->call('confirm')
        ->assertDispatched('mcp-tokens-changed')
        ->assertDispatched('notify', message: __('mcp.revoked'));

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('does not let a user revoke someone else\'s token through the confirm window', function () {
    $owner = User::factory()->admin()->create();
    app(CreateMcpToken::class)->handle($owner, 'Ofis', McpAccess::ReadOnly);
    $token = PersonalAccessToken::query()->sole();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Confirm::class, ['action' => 'revoke-mcp-token', 'id' => $token->id])
        ->assertDontSee('Ofis')
        ->call('confirm')
        ->assertNotFound();

    expect(PersonalAccessToken::query()->count())->toBe(1);
});
