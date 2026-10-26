<?php

use App\Actions\Account\AuthenticateUser;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('does not sign in a deactivated user, even with the right password', function () {
    $user = User::factory()->deactivated()->create(['password' => 'secret-pass']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('does not sign in an invited user who has no password yet', function (string $password) {
    $user = User::factory()->invited()->create();

    expect(fn () => app(AuthenticateUser::class)->handle($user->email, $password, false, '127.0.0.1'))
        ->toThrow(ValidationException::class);

    $this->assertGuest();
})->with(['empty' => [''], 'any text' => ['password']]);

it('still signs in an active user', function () {
    $user = User::factory()->developer()->create(['password' => 'secret-pass']);

    app(AuthenticateUser::class)->handle($user->email, 'secret-pass', false, '127.0.0.1');

    $this->assertAuthenticatedAs($user);
});

it('closes the session of a user who is deactivated while signed in', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get(route('app.profile'))->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user->fresh())->get(route('app.profile'))->assertRedirect(route('app.login'));

    $this->assertGuest();
});

it('applies the active check to livewire update requests too', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserIsActive::class);
});
