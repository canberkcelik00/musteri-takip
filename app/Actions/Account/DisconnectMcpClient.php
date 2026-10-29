<?php

namespace App\Actions\Account;

use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DisconnectMcpClient
{
    public function __construct(private readonly RevokeMcpTokens $revokeTokens) {}

    /**
     * Cut one of the user's own Claude connections; the app using it has to be approved again to get back in.
     *
     * @throws ModelNotFoundException when the connection is not the user's
     */
    public function handle(User $user, int $connectionId): void
    {
        $connection = McpConnection::query()->where('user_id', $user->id)->findOrFail($connectionId);

        $this->revokeTokens->handle($user, $connection->oauth_client_id);
    }
}
