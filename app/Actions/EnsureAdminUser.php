<?php

namespace App\Actions;

use App\Models\User;

class EnsureAdminUser
{
    /**
     * Create the first panel user from the `app.admin` config when it does not exist yet.
     *
     * Safe to run on every deploy: an existing user is never modified, and when
     * any admin value is empty nothing happens and null is returned.
     */
    public function handle(): ?User
    {
        /** @var array{name: ?string, email: ?string, password: ?string} $admin */
        $admin = config('app.admin');

        if (blank($admin['name']) || blank($admin['email']) || blank($admin['password'])) {
            return null;
        }

        return User::query()->firstOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password']],
        );
    }
}
