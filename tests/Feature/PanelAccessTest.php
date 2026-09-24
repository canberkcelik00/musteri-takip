<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('panel');
});

it('redirects guests from the panel to the login page', function () {
    $this->get('/panel')->assertRedirect('/panel/login');
});

it('opens the panel for a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel')
        ->assertOk();
});

it('redirects guests from the profile page to the login page', function () {
    $this->get('/panel/profile')->assertRedirect('/panel/login');
});

it('opens the profile page for a signed-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/panel/profile')
        ->assertOk();
});

it('shows the login page in Turkish', function () {
    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('lang="tr"', escape: false)
        ->assertSee('E-posta adresi')
        ->assertSee('Şifre')
        ->assertSee('Giriş yap');
});

it('signs in with correct credentials', function () {
    $user = User::factory()->create(['password' => 'gizli-sifre']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'gizli-sifre'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials with a Turkish message', function () {
    $user = User::factory()->create(['password' => 'gizli-sifre']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'yanlis-sifre'])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertSee('Bu kimlik bilgileri kayıtlarımızla eşleşmiyor.');

    $this->assertGuest();
});
