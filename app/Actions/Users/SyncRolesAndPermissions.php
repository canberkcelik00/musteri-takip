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
     * Make the roles and permissions tables match the `Permission` enum and the built-in roles.
     *
     * Safe to run on every deploy and from the console: nothing is duplicated and a permission that left the enum is
     * removed. The administrator always holds every permission. The developer gets its default list only when the role
     * is first created and, once, any permission added later that is in its default list (T-167); after that the roles
     * screen owns it (T-165). Roles the administrator made are never touched,
     * and role assignments of users neither.
     */
    public function handle(): void
    {
        $names = array_map(fn (Permission $permission): string => $permission->value, Permission::cases());

        $created = [];

        foreach ($names as $name) {
            if (PermissionModel::findOrCreate($name)->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        PermissionModel::query()->whereNotIn('name', $names)->delete();

        foreach (Role::cases() as $role) {
            $record = RoleModel::findOrCreate($role->value);

            $defaults = array_map(fn (Permission $permission): string => $permission->value, $role->permissions());

            if ($role === Role::Admin || $record->wasRecentlyCreated) {
                $record->syncPermissions($defaults);
            } elseif ($newDefaults = array_values(array_intersect($defaults, $created))) {
                $record->givePermissionTo($newDefaults);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
