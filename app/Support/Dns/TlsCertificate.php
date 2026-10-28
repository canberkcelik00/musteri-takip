<?php

namespace App\Support\Dns;

use Carbon\CarbonImmutable;

/**
 * What the SSL checks keep of a site's certificate (Faz 16).
 */
final readonly class TlsCertificate
{
    public function __construct(
        public CarbonImmutable $expiresAt,
        public ?string $issuer,
        public ?string $subject,
    ) {}
}
