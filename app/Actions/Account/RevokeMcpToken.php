<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RevokeMcpToken
{
    /**
     * Delete one of the user's own Claude access tokens; the connection using it stops working on its next call.
     *
     * @throws ModelNotFoundException when the token is not the user's
     */
    public function handle(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->firstOrFail()->delete();
    }
}
