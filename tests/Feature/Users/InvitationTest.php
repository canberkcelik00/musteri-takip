<?php

use App\Actions\Users\SendInvitation;
use App\Actions\Users\SyncRolesAndPermissions;
use App\Enums\Role;
use App\Livewire\Pages\Auth\AcceptInvitation;
use App\Livewire\Pages\Settings\Users;
use App\Livewire\Windows\InviteUser;
use App\Mail\InvitationMail;
use App\Models\User;
use App\Support\Ui\Navigation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function invitedUser(): User
{
    $user = User::factory()->invited()->create(['name' => 'Yeni Kişi', 'email' => 'yeni@example.com']);
    $user->forceFill(['invited_at' => now()])->save();

    return $user;
}

/**
 * The signed link the invitation mail carries.
 */
function invitationUrl(User $user): string
{
    $url = null;

    Mail::fake();
    app(SendInvitation::class)->handle($user);
    Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$url): bool {
        $url = $mail->url;

        return true;
    });

    return $url;
}

it('creates the user and sends the invitation from the window', function () {
    Mail::fake();

    Livewire::actingAs(User::factory()->admin()->create())->test(InviteUser::class)
        ->set('name', 'Ayşe Yılmaz')
        ->set('email', 'ayse@example.com')
        ->set('role', 'developer')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('close-window');

    $user = User::query()->where('email', 'ayse@example.com')->firstOrFail();

    expect($user->hasRole('developer'))->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and($user->isInvitationPending())->toBeTrue();

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo('ayse@example.com') && str_contains($mail->url, '/invitation/'.$user->id));
});

it('refuses a duplicate e-mail address and an unknown role in the window', function () {
    User::factory()->create(['email' => 'var@example.com']);

    Livewire::actingAs(User::factory()->admin()->create())->test(InviteUser::class)
        ->set('name', 'Biri')
        ->set('email', 'var@example.com')
        ->set('role', 'owner')
        ->call('save')
        ->assertHasErrors(['email', 'role']);
});

it('keeps the user when the invitation mail cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    Livewire::actingAs(User::factory()->admin()->create())->test(InviteUser::class)
        ->set('name', 'Ayşe')
        ->set('email', 'ayse@example.com')
        ->call('save')
        ->assertDispatched('notify', fn (string $event, array $params): bool => $params['tone'] === 'danger' && str_contains($params['message'], 'SMTP down'));

    expect(User::query()->where('email', 'ayse@example.com')->exists())->toBeTrue();
});

it('lets the invited person choose a password through the link and signs them in', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $this->get($url)->assertOk()->assertSee(__('users.accept_intro'));

    Livewire::withQueryParams(queryOf($url))->test(AcceptInvitation::class, ['user' => $user])
        ->set('password', 'gizli-sifre-1')
        ->set('password_confirmation', 'gizli-sifre-1')
        ->call('accept')
        ->assertRedirect(route('app.today'));

    expect($user->fresh()->hasPassword())->toBeTrue()
        ->and($user->fresh()->isInvitationPending())->toBeFalse()
        ->and(auth()->id())->toBe($user->id);
});

function queryOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query;
}

it('requires a password of at least 8 characters that is confirmed', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    Livewire::withQueryParams(queryOf($url))->test(AcceptInvitation::class, ['user' => $user])
        ->set('password', 'kisa')
        ->set('password_confirmation', 'baska')
        ->call('accept')
        ->assertHasErrors(['password']);

    expect($user->fresh()->hasPassword())->toBeFalse();
});

it('rejects a tampered link', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $this->get(str_replace('stamp=', 'stamp=1', $url))->assertForbidden();
});

it('rejects the link after 72 hours', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $this->travel(SendInvitation::VALID_HOURS + 1)->hours();

    $this->get($url)->assertForbidden();
});

it('shows a notice instead of the form on a second use', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $user->forceFill(['password' => 'baska-sifre-1'])->save();

    $this->get($url)->assertOk()->assertSee(__('users.invitation_invalid'))->assertDontSee(__('users.accept_intro'));
});

it('invalidates the earlier link when the invitation is sent again', function () {
    $user = invitedUser();
    $old = invitationUrl($user);

    $this->travel(5)->minutes();
    $new = invitationUrl($user);

    $this->get($old)->assertOk()->assertSee(__('users.invitation_invalid'));
    $this->get($new)->assertOk()->assertSee(__('users.accept_intro'));
});

it('shows a notice for the link of a deactivated user', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->get($url)->assertOk()->assertSee(__('users.invitation_invalid'));
});

it('does not accept the invitation through the page action once it is used', function () {
    $user = invitedUser();
    $url = invitationUrl($user);

    $component = Livewire::withQueryParams(queryOf($url))->test(AcceptInvitation::class, ['user' => $user]);

    $user->forceFill(['password' => 'baska-sifre-1'])->save();

    $component->set('password', 'gizli-sifre-1')->set('password_confirmation', 'gizli-sifre-1')->call('accept')->assertSet('invalid', true);

    expect(auth()->check())->toBeFalse();
});

it('does not let the invited user sign in before choosing a password, and a developer lands on the customers', function () {
    $user = invitedUser();
    app(SyncRolesAndPermissions::class)->handle();
    $user->assignRole(Role::Developer->value);
    $url = invitationUrl($user);

    Livewire::withQueryParams(queryOf($url))->test(AcceptInvitation::class, ['user' => $user])
        ->set('password', 'gizli-sifre-1')
        ->set('password_confirmation', 'gizli-sifre-1')
        ->call('accept');

    $this->get(route('app.today'))->assertRedirect(route('app.customers.index'));
});

it('lists the users with their state for an administrator', function () {
    User::factory()->admin()->create(['name' => 'Yönetici Bir']);
    invitedUser();
    User::factory()->deactivated()->create(['name' => 'Pasif Kişi']);

    Livewire::actingAs(User::factory()->admin()->create())->test(Users::class)
        ->assertSee('Yönetici Bir')
        ->assertSee('Yeni Kişi')
        ->assertSee(__('users.status_invited'))
        ->assertSee(__('users.status_deactivated'));
});

it('forbids a developer the users screen and the invite window', function () {
    $this->actingAs(User::factory()->developer()->create())->get(route('app.users'))->assertForbidden();

    Livewire::actingAs(User::factory()->developer()->create())->test(InviteUser::class)->assertForbidden();
    Livewire::actingAs(User::factory()->developer()->create())->test(Users::class)->assertForbidden();
});

it('sends the invitation again from the list', function () {
    Mail::fake();
    $user = invitedUser();

    Livewire::actingAs(User::factory()->admin()->create())->test(Users::class)->call('resend', $user->id);

    Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail): bool => $mail->hasTo('yeni@example.com'));
});

it('changes the role and the state of a user from the list', function () {
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->developer()->create();

    $page = Livewire::actingAs($admin)->test(Users::class);

    $page->call('changeRole', $developer->id);
    expect($developer->fresh()->hasRole('admin'))->toBeTrue();

    $page->call('deactivate', $developer->id);
    expect($developer->fresh()->isActive())->toBeFalse();

    $page->call('reactivate', $developer->id);
    expect($developer->fresh()->isActive())->toBeTrue();
});

it('lets an administrator neither deactivate nor demote themself', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Users::class)
        ->call('deactivate', $admin->id)
        ->assertDispatched('notify', fn (string $event, array $params): bool => $params['message'] === __('users.own_account'))
        ->call('changeRole', $admin->id)
        ->assertDispatched('notify', fn (string $event, array $params): bool => $params['tone'] === 'danger');

    expect($admin->fresh()->isActive())->toBeTrue()->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('keeps the last active administrator', function () {
    $actor = User::factory()->admin()->deactivated()->create();
    $last = User::factory()->admin()->create();

    $page = Livewire::actingAs($actor)->test(Users::class);
    $page->call('deactivate', $last->id);
    $page->call('changeRole', $last->id);

    expect($last->fresh()->isActive())->toBeTrue()->and($last->fresh()->hasRole('admin'))->toBeTrue();
});

it('lets one administrator deactivate another when one stays', function () {
    $actor = User::factory()->admin()->create();
    $other = User::factory()->admin()->create();

    Livewire::actingAs($actor)->test(Users::class)->call('deactivate', $other->id);

    expect($other->fresh()->isActive())->toBeFalse();
});

it('puts the users item in the administrator menu only', function () {
    $this->actingAs(User::factory()->admin()->create());
    $labels = collect(Navigation::entries(route('app.today')))->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry])->pluck('label');

    expect($labels)->toContain('ui.nav.users');
});
