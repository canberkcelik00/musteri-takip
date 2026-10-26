<?php

namespace App\Actions\Users;

use App\Models\User;

class ReactivateUser
{
    public function handle(User $user): void
    {
        $user->forceFill(['deactivated_at' => null])->save();
    }
}
