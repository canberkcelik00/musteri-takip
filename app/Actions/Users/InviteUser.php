<?php

namespace App\Actions\Users;

use App\Exceptions\InvitationNotSent;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

class InviteUser
{
    public function __construct(private SendInvitation $sendInvitation) {}

    /**
     * Create the user without a password and e-mail the invitation. The user is kept when the e-mail fails.
     *
     * @throws InvitationNotSent
     */
    public function handle(string $name, string $email, RoleModel $role): User
    {
        $user = User::query()->create(['name' => $name, 'email' => $email]);
        $user->forceFill(['invited_at' => now()])->save();
        $user->assignRole($role);

        $this->sendInvitation->handle($user);

        return $user;
    }
}
