<?php

namespace App\Actions\Users;

use App\Exceptions\InvalidUserChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

class UpdateUser
{
    public function __construct(private ChangeUserRole $changeUserRole) {}

    /**
     * Change the user's name and role. The e-mail address stays: it is what the invitation and the sign-in use.
     * The role follows the rules of {@see ChangeUserRole}; an unchanged role is not checked, so a person can
     * rename their own account.
     *
     * @throws InvalidUserChange
     */
    public function handle(User $actor, User $user, string $name, RoleModel $role): User
    {
        return DB::transaction(function () use ($actor, $user, $name, $role): User {
            if (! $user->hasRole($role->name)) {
                $this->changeUserRole->handle($actor, $user, $role);
            }

            $user->update(['name' => trim($name)]);

            return $user;
        });
    }
}
