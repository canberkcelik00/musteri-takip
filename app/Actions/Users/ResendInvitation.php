<?php

namespace App\Actions\Users;

use App\Exceptions\InvalidInvitation;
use App\Exceptions\InvitationNotSent;
use App\Models\User;

class ResendInvitation
{
    public function __construct(private SendInvitation $sendInvitation) {}

    /**
     * Send a fresh invitation; the earlier link stops working.
     *
     * @throws InvalidInvitation
     * @throws InvitationNotSent
     */
    public function handle(User $user): void
    {
        $this->sendInvitation->handle($user);
    }
}
