<?php

namespace App\Actions\Users;

use App\Exceptions\InvalidInvitation;
use App\Exceptions\InvitationNotSent;
use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendInvitation
{
    /** Hours the invitation link stays valid. */
    public const int VALID_HOURS = 72;

    /**
     * Stamp the user as invited now and e-mail the signed link. The link carries the invitation time, so a newer
     * invitation makes every earlier link invalid. The user stays saved when the e-mail fails.
     *
     * @throws InvalidInvitation the user already chose a password or is deactivated
     * @throws InvitationNotSent
     */
    public function handle(User $user): void
    {
        if (! $user->isInvitationPending()) {
            throw new InvalidInvitation;
        }

        $user->forceFill(['invited_at' => now()])->save();

        $url = URL::temporarySignedRoute(
            'app.invitation',
            now()->addHours(self::VALID_HOURS),
            ['user' => $user->id, 'stamp' => $user->invited_at?->getTimestamp()],
        );

        try {
            Mail::to($user->email)->send(new InvitationMail($user, $url, self::VALID_HOURS));
        } catch (Throwable $exception) {
            throw new InvitationNotSent($exception);
        }
    }
}
