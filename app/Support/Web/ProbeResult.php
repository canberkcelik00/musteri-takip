<?php

namespace App\Support\Web;

/**
 * The answer of one site probe (Faz 16): the site answered, or it did not.
 */
final readonly class ProbeResult
{
    public function __construct(
        public bool $up,
        public ?int $status,
        public ?string $reason,
    ) {}
}
