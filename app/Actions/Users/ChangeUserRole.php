<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\InvalidUserChange;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

class ChangeUserRole
{
    /**
     * Give the user the role. An administrator cannot change their own role, and the last active administrator
     * stays one.
     *
     * @throws InvalidUserChange
     */
    public function handle(User $actor, User $user, RoleModel $role): void
    {
        if ($actor->is($user)) {
            throw InvalidUserChange::ownAccount();
        }

        if ($role->name !== Role::Admin->value && $user->isActive() && $user->hasRole(Role::Admin->value) && User::activeAdministratorCount() <= 1) {
            throw InvalidUserChange::lastAdministrator();
        }

        $user->syncRoles($role);
    }
}
