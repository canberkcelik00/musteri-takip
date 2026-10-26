<?php

namespace App\Actions\Users;

use App\Exceptions\InvalidInvitation;
use App\Models\User;

class AcceptInvitation
{
    /**
     * Set the password the invited person chose. The invitation must be the user's latest (`$stamp` is its
     * time), not expired (`$expires` is a timestamp), not accepted yet, and the user must not be deactivated.
     *
     * @throws InvalidInvitation
     */
    public function handle(User $user, int $stamp, int $expires, string $password): User
    {
        if (! $this->isUsable($user, $stamp, $expires)) {
            throw new InvalidInvitation;
        }

        $user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();

        return $user;
    }

    public function isUsable(User $user, int $stamp, int $expires): bool
    {
        return $user->isInvitationPending()
            && $user->invited_at?->getTimestamp() === $stamp
            && now()->getTimestamp() <= $expires;
    }
}
