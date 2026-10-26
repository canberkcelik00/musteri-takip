<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\InvalidUserChange;
use App\Models\User;

class ChangeUserRole
{
    /**
     * Give the user the role. An administrator cannot change their own role, and the last active administrator
     * stays one.
     *
     * @throws InvalidUserChange
     */
    public function handle(User $actor, User $user, Role $role): void
    {
        if ($actor->is($user)) {
            throw InvalidUserChange::ownAccount();
        }

        if ($role !== Role::Admin && $user->isActive() && $user->hasRole(Role::Admin->value) && User::activeAdministratorCount() <= 1) {
            throw InvalidUserChange::lastAdministrator();
        }

        $user->syncRoles($role->value);
    }
}
