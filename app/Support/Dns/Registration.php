<?php

namespace App\Support\Dns;

use Carbon\CarbonImmutable;

/**
 * What the WHOIS checks keep of a domain registration (Faz 16): the day it expires and who holds it.
 */
final readonly class Registration
{
    public function __construct(
        public ?CarbonImmutable $expiresOn,
        public ?string $registrar,
    ) {}
}
