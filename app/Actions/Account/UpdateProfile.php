<?php

namespace App\Actions\Account;

use App\Models\User;

class UpdateProfile
{
    public function handle(User $user, string $name, string $email): User
    {
        $user->forceFill(['name' => $name, 'email' => $email])->save();

        return $user;
    }
}
