<?php

namespace App\Actions\Servers;

/**
 * What one run of {@see RefreshStaleUsages} did: accounts refreshed, accounts whose refresh
 * failed, accounts left for the next run when the time budget ran out, and cache rows removed
 * because no linked, active service needs them any more.
 */
final readonly class UsageRefreshResult
{
    public function __construct(
        public int $refreshed,
        public int $failed,
        public int $remaining,
        public int $removed,
    ) {}
}
