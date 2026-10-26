<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\User;

class EnsureAdminUser
{
    /**
     * Create the first panel user from the `app.admin` config when it does not exist yet, and
     * make it an administrator (the roles must be synced first, see `SyncRolesAndPermissions`).
     *
     * Safe to run on every deploy: an existing user keeps its data and any role it already has
     * (a demoted account is not promoted again), and when any admin value is empty nothing
     * happens and null is returned.
     */
    public function handle(): ?User
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        $admin = config('app.admin');

        if (blank($admin['name']) || blank($admin['email']) || blank($admin['password'])) {
            return null;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password']],
        );

        if ($user->roles()->doesntExist()) {
            $user->assignRole(Role::Admin->value);
        }

        return $user;
    }
}
