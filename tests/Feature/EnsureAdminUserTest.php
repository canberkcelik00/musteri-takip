<?php

use App\Actions\EnsureAdminUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set('app.admin', [
        'name' => 'İşletme Sahibi',
        'email' => 'sahip@example.com',
        'password' => 'ilk-sifre',
    ]);
});

it('creates the admin user from config', function () {
    $user = app(EnsureAdminUser::class)->handle();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('İşletme Sahibi')
        ->and($user->email)->toBe('sahip@example.com')
        ->and($user->getAuthPassword())->not->toBe('ilk-sifre')
        ->and(Hash::check('ilk-sifre', $user->getAuthPassword()))->toBeTrue()
        ->and(User::count())->toBe(1);
});

it('does not create a second user or overwrite the password when run again', function () {
    app(EnsureAdminUser::class)->handle();

    User::where('email', 'sahip@example.com')->update(['password' => Hash::make('degistirilmis-sifre')]);

    $user = app(EnsureAdminUser::class)->handle();

    expect(User::count())->toBe(1)
        ->and($user->wasRecentlyCreated)->toBeFalse()
        ->and(Hash::check('degistirilmis-sifre', $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('does nothing when an admin value is empty', function (string $key, ?string $value) {
    config()->set("app.admin.{$key}", $value);

    expect(app(EnsureAdminUser::class)->handle())->toBeNull()
        ->and(User::count())->toBe(0);
})->with([
    'empty password' => ['password', ''],
    'missing password' => ['password', null],
    'empty email' => ['email', ''],
    'empty name' => ['name', ''],
]);

it('reports a created admin from the command', function () {
    $this->artisan('app:create-admin')
        ->expectsOutputToContain('Yönetici kullanıcı oluşturuldu: sahip@example.com')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('reports an existing admin from the command', function () {
    app(EnsureAdminUser::class)->handle();

    $this->artisan('app:create-admin')
        ->expectsOutputToContain('Yönetici kullanıcı zaten var')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('skips quietly from the command when admin values are empty', function () {
    config()->set('app.admin.password', null);

    $this->artisan('app:create-admin')
        ->expectsOutputToContain('yönetici oluşturma atlandı')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});
