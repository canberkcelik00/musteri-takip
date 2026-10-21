<?php

namespace App\Actions\Servers;

/**
 * What one run of {@see RefreshStaleServerTotals} did: servers refreshed, servers whose refresh
 * failed, and stored totals removed because the server is archived or no longer a WHM server.
 */
final readonly class ServerTotalsRefreshResult
{
    public function __construct(
        public int $refreshed,
        public int $failed,
        public int $removed,
    ) {}
}
