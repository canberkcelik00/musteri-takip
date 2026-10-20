<?php

namespace App\Exceptions;

use App\Enums\RemoteServerErrorReason;
use RuntimeException;

/**
 * The single failure type of the WHM/cPanel clients. The message is Turkish and never carries
 * the token or the server's raw response.
 */
class RemoteServerError extends RuntimeException
{
    public function __construct(string $message, public readonly RemoteServerErrorReason $reason)
    {
        parent::__construct($message);
    }

    public static function because(RemoteServerErrorReason $reason): self
    {
        return new self(__("servers.connection_{$reason->value}"), $reason);
    }
}
