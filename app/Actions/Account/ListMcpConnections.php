<?php

namespace App\Actions\Account;

use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Passport\Passport;

class ListMcpConnections
{
    /**
     * The user's Claude connections that still hold a live token (an access token not expired, or a refresh token
     * that can still renew one), newest first, each with the time its last token was issued.
     *
     * @return Collection<int, array{connection: McpConnection, last_token_at: ?string}>
     */
    public function handle(User $user): Collection
    {
        $live = Passport::token()->newQuery()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())));

        $lastIssued = $live->clone()->selectRaw('client_id, max(created_at) as last_token_at')->groupBy('client_id')
            ->pluck('last_token_at', 'client_id');

        return McpConnection::query()
            ->where('user_id', $user->id)
            ->whereIn('oauth_client_id', $lastIssued->keys())
            ->with('client')
            ->latest('id')
            ->get()
            ->map(fn (McpConnection $connection): array => [
                'connection' => $connection,
                'last_token_at' => isset($lastIssued[$connection->oauth_client_id]) ? (string) $lastIssued[$connection->oauth_client_id] : null,
            ]);
    }
}
