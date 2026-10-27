<?php

namespace App\Actions\Domains;

/**
 * What one run of {@see CheckCloudflareDomains} did: domains checked, domains whose lookup
 * failed, and domains left for the next run when the time budget ran out.
 */
final readonly class DomainCheckResult
{
    public function __construct(
        public int $checked,
        public int $failed,
        public int $remaining,
    ) {}
}
