<?php

namespace App\Support\Dns;

use Carbon\CarbonImmutable;

/**
 * What the WHOIS checks read of a domain registration (Faz 16): the day it expires, who holds it and the
 * name servers the registry has on record (lower case; not stored, only shown live).
 */
final readonly class Registration
{
    /**
     * @param  list<string>  $nameservers
     */
    public function __construct(
        public ?CarbonImmutable $expiresOn,
        public ?string $registrar,
        public array $nameservers = [],
    ) {}
}
