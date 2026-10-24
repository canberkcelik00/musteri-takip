<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePassword
{
    /**
     * Set a new password; the current one must be given and correct.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('auth.password')]);
        }

        $user->forceFill(['password' => $newPassword])->save();
    }
}
