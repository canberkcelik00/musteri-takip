<?php

namespace App\Listeners;

use App\Enums\McpAccess;
use App\Models\McpConnection;
use Laravel\Passport\Events\AccessTokenCreated;

/**
 * Remembers a Claude connection the first time an OAuth client gets a token for a user (U-43), so the profile lists it
 * and has a row to hold its access; a renewal of the token finds the row and changes nothing.
 */
class RecordMcpConnection
{
    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null) {
            return;
        }

        McpConnection::query()->firstOrCreate(
            ['user_id' => (int) $event->userId, 'oauth_client_id' => $event->clientId],
            ['access' => McpAccess::ReadOnly],
        );
    }
}
