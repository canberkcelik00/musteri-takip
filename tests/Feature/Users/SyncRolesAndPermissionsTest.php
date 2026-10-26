<?php

use App\Actions\Users\SyncRolesAndPermissions;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('creates every role and permission of the enums', function () {
    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::pluck('name')->sort()->values()->all())->toBe(['admin', 'developer'])
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));
});

it('does not add records when run twice', function () {
    app(SyncRolesAndPermissions::class)->handle();
    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::count())->toBe(count(Role::cases()))
        ->and(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(RoleModel::findByName('admin')->permissions()->count())->toBe(count(Permission::cases()));
});

it('gives each role the permissions of its enum mapping', function () {
    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::findByName('admin')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::cases())->map->value->sort()->values()->all())
        ->and(RoleModel::findByName('developer')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customers.manage', 'services.view']);
});

it('keeps a developer away from money permissions', function () {
    $developer = User::factory()->developer()->create();

    expect($developer->can(Permission::MoneyView->value))->toBeFalse()
        ->and($developer->can(Permission::MoneyManage->value))->toBeFalse()
        ->and($developer->can(Permission::UsersManage->value))->toBeFalse()
        ->and($developer->can(Permission::CustomersManage->value))->toBeTrue();
});

it('lets an administrator do everything', function () {
    $admin = User::factory()->admin()->create();

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue();
    }
});

it('keeps the developer rights the roles screen changed, restores the administrator and removes a stale permission', function () {
    app(SyncRolesAndPermissions::class)->handle();

    RoleModel::findByName('developer')->givePermissionTo(Permission::MoneyView->value);
    RoleModel::findByName('admin')->revokePermissionTo(Permission::UsersManage->value);
    PermissionModel::create(['name' => 'old.permission']);

    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::findByName('developer')->hasPermissionTo(Permission::MoneyView->value))->toBeTrue()
        ->and(RoleModel::findByName('admin')->permissions()->count())->toBe(count(Permission::cases()))
        ->and(PermissionModel::where('name', 'old.permission')->exists())->toBeFalse();
});

it('does not touch a role the administrator made', function () {
    app(SyncRolesAndPermissions::class)->handle();

    RoleModel::create(['name' => 'Destek'])->givePermissionTo(Permission::MoneyView->value);

    app(SyncRolesAndPermissions::class)->handle();

    expect(RoleModel::findByName('Destek')->permissions->pluck('name')->all())->toBe([Permission::MoneyView->value]);
});

it('does not touch the roles of existing users', function () {
    $developer = User::factory()->developer()->create();

    app(SyncRolesAndPermissions::class)->handle();

    expect($developer->fresh()->hasRole(Role::Developer->value))->toBeTrue();
});
