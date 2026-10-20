<?php

namespace App\Actions\Servers;

/**
 * The outcome of a connection test: `message` is a Turkish sentence for the user and never
 * carries the token or the server's raw response.
 */
final readonly class ServerConnectionResult
{
    public function __construct(
        public bool $successful,
        public string $message,
    ) {}
}
