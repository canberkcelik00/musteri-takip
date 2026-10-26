<?php

namespace App\Actions\Users;

use App\Enums\Permission;
use App\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class SyncRolesAndPermissions
{
    /**
     * Make the roles and permissions tables match the `Role` and `Permission` enums.
     *
     * Safe to run on every deploy and from the console: nothing is duplicated, a permission
     * that left the enum is removed, and role assignments of users are never touched.
     */
    public function handle(): void
    {
        $names = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());

        foreach ($names as $name) {
            PermissionModel::findOrCreate($name);
        }

        PermissionModel::query()->whereNotIn('name', $names)->delete();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value)->syncPermissions(
                array_map(fn (Permission $permission): string => $permission->value, $role->permissions()),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
