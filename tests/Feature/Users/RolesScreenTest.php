<?php

use App\Actions\Roles\SaveRole;
use App\Actions\Users\SyncRolesAndPermissions;
use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Pages\Settings\Roles;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\EditRole;
use App\Models\User;
use App\Support\Ui\Navigation;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

function roleNamed(string $name): RoleModel
{
    return RoleModel::query()->where('name', $name)->firstOrFail();
}

function rolePermissions(string $name): array
{
    return roleNamed($name)->permissions()->pluck('name')->sort()->values()->all();
}

it('adds a role with the ticked permissions', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(EditRole::class)
        ->set('name', 'Destek')
        ->set('permissions', ['customers.manage', 'quotes.manage'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('close-window');

    expect(rolePermissions('Destek'))->toBe(['customers.manage', 'quotes.manage']);
});

it('lets a user of the new role into the allowed page and refuses the others', function () {
    app(SyncRolesAndPermissions::class)->handle();
    app(SaveRole::class)->handle(null, 'Destek', [Permission::MoneyView]);

    $user = User::factory()->create();
    $user->assignRole('Destek');

    $this->actingAs($user)->get(route('app.charges.index'))->assertOk();
    $this->actingAs($user)->get(route('app.catalog.services'))->assertForbidden();
});

it('shows the money to a developer once the money permission is given and hides it again when taken back', function () {
    $developer = User::factory()->developer()->create();

    expect($developer->can('money.view'))->toBeFalse();

    $admin = User::factory()->admin()->create();
    $component = Livewire::actingAs($admin)->test(EditRole::class, ['role' => roleNamed('developer')->id]);

    $component->set('permissions', ['customers.manage', 'services.view', 'money.view'])->call('save')->assertHasNoErrors();
    expect($developer->fresh()->can('money.view'))->toBeTrue();

    $component->set('permissions', ['customers.manage', 'services.view'])->call('save');
    expect($developer->fresh()->can('money.view'))->toBeFalse();
});

it('adds the permission a chosen one requires', function () {
    Livewire::actingAs(User::factory()->admin()->create())->test(EditRole::class)
        ->set('name', 'Muhasebe')
        ->set('permissions', ['money.manage', 'services.manage'])
        ->call('save');

    expect(rolePermissions('Muhasebe'))->toBe(['money.manage', 'money.view', 'services.manage', 'services.view']);
});

it('renames a custom role and a built-in one by its shown name', function () {
    $admin = User::factory()->admin()->create();
    app(SaveRole::class)->handle(null, 'Destek', []);

    Livewire::actingAs($admin)->test(EditRole::class, ['role' => roleNamed('Destek')->id])
        ->set('name', 'Destek Ekibi')->call('save')->assertHasNoErrors();

    Livewire::actingAs($admin)->test(EditRole::class, ['role' => roleNamed('developer')->id])
        ->set('name', 'Geliştirici')->call('save')->assertHasNoErrors();

    expect(RoleModel::query()->where('name', 'Destek Ekibi')->exists())->toBeTrue()
        ->and(roleNamed('developer')->label)->toBe('Geliştirici')
        ->and(Role::labelFor('developer'))->toBe('Geliştirici')
        ->and(User::factory()->developer()->create()->hasRole('developer'))->toBeTrue();

    Livewire::actingAs($admin)->test(EditRole::class, ['role' => roleNamed('developer')->id])
        ->set('name', 'Yazılımcı')->call('save')->assertHasNoErrors();

    expect(roleNamed('developer')->label)->toBeNull()
        ->and(Role::labelFor('developer'))->toBe('Yazılımcı');
});

it('keeps the renamed developer role when the roles are synced again', function () {
    app(SyncRolesAndPermissions::class)->handle();
    roleNamed('developer')->update(['label' => 'Geliştirici']);

    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::query()->where('name', 'developer')->count())->toBe(1)
        ->and(Role::labelFor('developer'))->toBe('Geliştirici');
});

it('refuses a name that is taken or reserved', function () {
    $admin = User::factory()->admin()->create();
    app(SaveRole::class)->handle(null, 'Destek', []);

    foreach (['Destek', 'destek', 'admin', 'Yönetici', 'Yazılımcı'] as $name) {
        Livewire::actingAs($admin)->test(EditRole::class)->set('name', $name)->call('save')->assertHasErrors('form');
    }

    Livewire::actingAs($admin)->test(EditRole::class, ['role' => roleNamed('developer')->id])
        ->set('name', 'Destek')->call('save')->assertHasErrors('form');
});

it('does not change the administrator role', function () {
    $admin = User::factory()->admin()->create();
    $role = roleNamed('admin');

    Livewire::actingAs($admin)->test(EditRole::class, ['role' => $role->id])
        ->set('permissions', [])
        ->call('save')
        ->assertHasErrors('form');

    expect(rolePermissions('admin'))->toHaveCount(count(Permission::cases()));
});

it('deletes a role nobody holds and keeps a role someone holds and the built-in roles', function () {
    $admin = User::factory()->admin()->create();
    $free = app(SaveRole::class)->handle(null, 'Boş', []);
    $held = app(SaveRole::class)->handle(null, 'Dolu', []);
    User::factory()->create()->assignRole($held);

    Livewire::actingAs($admin)->test(Confirm::class, ['action' => 'delete-role', 'id' => $free->id])->call('confirm')->assertHasNoErrors();
    Livewire::actingAs($admin)->test(Confirm::class, ['action' => 'delete-role', 'id' => $held->id])->call('confirm')->assertHasErrors('form');
    Livewire::actingAs($admin)->test(Confirm::class, ['action' => 'delete-role', 'id' => roleNamed('developer')->id])->call('confirm')->assertHasErrors('form');

    expect(RoleModel::query()->where('name', 'Boş')->exists())->toBeFalse()
        ->and(RoleModel::query()->where('name', 'Dolu')->exists())->toBeTrue()
        ->and(RoleModel::query()->where('name', 'developer')->exists())->toBeTrue();
});

it('lists the roles with their counts for an administrator', function () {
    $admin = User::factory()->admin()->create();
    app(SaveRole::class)->handle(null, 'Destek', [Permission::CustomersManage]);

    Livewire::actingAs($admin)->test(Roles::class)->assertSee('Destek')->assertSee('Yönetici');
});

it('forbids a developer the roles screen and its windows', function () {
    $this->actingAs(User::factory()->developer()->create())->get(route('app.roles'))->assertForbidden();

    Livewire::actingAs(User::factory()->developer()->create())->test(Roles::class)->assertForbidden();
    Livewire::actingAs(User::factory()->developer()->create())->test(EditRole::class)->assertForbidden();
});

it('puts the roles item in the administrator menu only and highlights it alone', function () {
    $this->actingAs(User::factory()->admin()->create());
    $items = collect(Navigation::entries(route('app.roles')))->flatMap(fn (array $entry): array => $entry['items'] ?? [$entry]);

    expect($items->pluck('label'))->toContain('ui.nav.roles')
        ->and($items->where('active', true)->pluck('label')->all())->toBe(['ui.nav.roles']);
});

it('translates every permission label and hint', function () {
    foreach (Permission::cases() as $permission) {
        expect($permission->getLabel())->not->toStartWith('enums.')
            ->and($permission->getDescription())->not->toStartWith('enums.');
    }
});
