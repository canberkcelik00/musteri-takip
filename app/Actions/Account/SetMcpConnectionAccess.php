<?php

namespace App\Actions\Account;

use App\Enums\McpAccess;
use App\Models\McpConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SetMcpConnectionAccess
{
    /**
     * Let one of the user's own Claude connections only read, or read and use the write tools (U-43).
     *
     * @throws ModelNotFoundException when the connection is not the user's
     */
    public function handle(User $user, int $connectionId, McpAccess $access): McpConnection
    {
        $connection = McpConnection::query()->where('user_id', $user->id)->findOrFail($connectionId);
        $connection->update(['access' => $access]);

        return $connection;
    }
}
