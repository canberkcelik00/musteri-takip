<?php

namespace App\Support\Servers;

use Carbon\CarbonImmutable;

/**
 * A hosting account on a server: a WHM account, or one cPanel domain. `key` identifies it on
 * that server and is stored as `customer_service_items.remote_key`.
 */
final readonly class RemoteAccount
{
    public function __construct(
        public string $key,
        public string $domain,
        public string $username,
        public ?string $email,
        public ?string $packageName,
        public ?CarbonImmutable $startDate,
        public bool $suspended,
    ) {}
}
