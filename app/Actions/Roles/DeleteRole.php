<?php

namespace App\Actions\Roles;

use App\Enums\Role;
use App\Exceptions\InvalidRole;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class DeleteRole
{
    /**
     * Delete a role the administrator made. The built-in roles stay, and so does a role someone still holds.
     *
     * @throws InvalidRole
     */
    public function handle(RoleModel $role): void
    {
        if (Role::tryFrom($role->name) !== null) {
            throw InvalidRole::builtIn();
        }

        if ($role->users()->exists()) {
            throw InvalidRole::inUse();
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
