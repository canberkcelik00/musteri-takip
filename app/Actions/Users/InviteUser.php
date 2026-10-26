<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\InvitationNotSent;
use App\Models\User;

class InviteUser
{
    public function __construct(private SendInvitation $sendInvitation) {}

    /**
     * Create the user without a password and e-mail the invitation. The user is kept when the e-mail fails.
     *
     * @throws InvitationNotSent
     */
    public function handle(string $name, string $email, Role $role): User
    {
        $user = User::query()->create(['name' => $name, 'email' => $email]);
        $user->forceFill(['invited_at' => now()])->save();
        $user->assignRole($role->value);

        $this->sendInvitation->handle($user);

        return $user;
    }
}
