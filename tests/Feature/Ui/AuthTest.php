<?php

use App\Actions\Account\AuthenticateUser;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Pages\Auth\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('does not show the glass interface to a guest', function () {
    $this->get('/')->assertRedirect(route('app.login'));
    $this->get('/profile')->assertRedirect(route('app.login'));
});

it('shows the login page to a guest and sends a signed-in user home', function () {
    $this->get('/login')->assertOk()->assertSee(__('ui.login.submit'));

    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('app.today'));
});

it('signs in with the right credentials', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.today'));

    $this->assertAuthenticatedAs($user);
});

it('goes back to the page that was asked for before signing in', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    $this->get('/profile')->assertRedirect(route('app.login'));

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertRedirect(route('app.profile'));
});

it('queues the remember cookie only when asked to', function (bool $remember) {
    $user = User::factory()->create(['password' => 'secret-pass']);

    app(AuthenticateUser::class)->handle($user->email, 'secret-pass', $remember, '127.0.0.1');

    $names = collect(app('cookie')->getQueuedCookies())->map(fn ($cookie) => $cookie->getName())->implode(',');

    expect(str_contains($names, 'remember_web'))->toBe($remember);
})->with([true, false]);

it('rejects a wrong password with a message on the e-mail field', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

it('requires both fields', function () {
    Livewire::test(Login::class)
        ->call('login')
        ->assertHasErrors(['email' => 'required', 'password' => 'required']);
});

it('locks the sign-in after too many failed attempts, even for the right password', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);
    $component = Livewire::test(Login::class)->set('email', $user->email);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS) as $attempt) {
        $component->set('password', 'wrong')->call('login')->assertHasErrors(['email' => __('auth.failed')]);
    }

    $component->set('password', 'secret-pass')->call('login');

    $component->assertHasErrors('email');
    expect($component->errors()->first('email'))->toStartWith('Çok fazla giriş denemesi');
    $this->assertGuest();

    RateLimiter::clear(Str::transliterate(Str::lower($user->email).'|127.0.0.1'));
});

it('signs out and sends the user to the login page', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('app.logout'))
        ->assertRedirect(route('app.login'));

    $this->assertGuest();
});

it('shows the profile and saves name and e-mail', function () {
    $user = User::factory()->create(['name' => 'Eski Ad']);

    $this->actingAs($user)->get(route('app.profile'))->assertOk()->assertSee('Eski Ad');

    Livewire::actingAs($user)->test(Profile::class)
        ->set('name', 'Yeni Ad')
        ->set('email', 'yeni@example.com')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: __('ui.profile.saved'));

    expect($user->fresh())->name->toBe('Yeni Ad')->email->toBe('yeni@example.com');
});

it('does not take another user\'s e-mail address', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('email', $other->email)
        ->call('saveProfile')
        ->assertHasErrors(['email' => 'unique']);
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'not-the-password')
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'new-password-1')
        ->call('changePassword')
        ->assertHasErrors(['current_password' => __('auth.password')]);

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'old-password')
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'new-password-1')
        ->call('changePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertDispatched('notify', message: __('ui.profile.password_changed'));

    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeTrue();
});

it('asks for a confirmed password of at least eight characters', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'old-password')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('changePassword')
        ->assertHasErrors('password');

    Livewire::actingAs($user)->test(Profile::class)
        ->set('current_password', 'old-password')
        ->set('password', 'new-password-1')
        ->set('password_confirmation', 'different-1')
        ->call('changePassword')
        ->assertHasErrors(['password' => 'confirmed']);
});
