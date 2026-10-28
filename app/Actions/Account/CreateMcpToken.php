<?php

namespace App\Actions\Account;

use App\Enums\McpAccess;
use App\Models\User;

class CreateMcpToken
{
    public const VALID_FOR_DAYS = 90;

    /**
     * Create a Claude access token for the user (U-42). Only its hash is stored; the plain text returned here is the
     * one chance to see it.
     */
    public function handle(User $user, string $name, McpAccess $access): string
    {
        return $user->createToken($name, $access->abilities(), now()->addDays(self::VALID_FOR_DAYS))->plainTextToken;
    }
}
