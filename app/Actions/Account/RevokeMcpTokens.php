<?php

namespace App\Actions\Account;

use App\Models\McpConnection;
use App\Models\User;
use Laravel\Passport\Passport;

class RevokeMcpTokens
{
    /**
     * Revoke the user's access tokens, refresh tokens and unused authorization codes, of one OAuth client or of all of
     * them, and forget the connections; whoever holds them gets 401 on the next call.
     */
    public function handle(User $user, ?string $clientId = null): void
    {
        $tokens = Passport::token()->newQuery()->where('user_id', $user->id)
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId));

        Passport::refreshToken()->newQuery()->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
        $tokens->update(['revoked' => true]);

        Passport::authCode()->newQuery()->where('user_id', $user->id)
            ->when($clientId !== null, fn ($query) => $query->where('client_id', $clientId))
            ->update(['revoked' => true]);

        McpConnection::query()->where('user_id', $user->id)
            ->when($clientId !== null, fn ($query) => $query->where('oauth_client_id', $clientId))
            ->delete();
    }
}
