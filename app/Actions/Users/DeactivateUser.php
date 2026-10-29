<?php

namespace App\Actions\Users;

use App\Actions\Account\RevokeMcpTokens;
use App\Enums\Role;
use App\Exceptions\InvalidUserChange;
use App\Models\User;

class DeactivateUser
{
    /**
     * Block the user from signing in; an open session ends on the next request and their Claude connections are
     * cut (U-43). An administrator cannot deactivate themself, and the last active administrator stays active.
     *
     * @throws InvalidUserChange
     */
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw InvalidUserChange::ownAccount();
        }

        if ($user->isActive() && $user->hasRole(Role::Admin->value) && User::activeAdministratorCount() <= 1) {
            throw InvalidUserChange::lastAdministrator();
        }

        $user->forceFill(['deactivated_at' => $user->deactivated_at ?? now()])->save();
        app(RevokeMcpTokens::class)->handle($user);
    }
}
