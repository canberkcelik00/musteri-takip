<?php

namespace App\Actions\Servers;

/**
 * A mailbox row of the "Kullanım" window: its meter and whether the server suspended its login
 * or its incoming mail.
 */
final readonly class UsageMailbox
{
    public function __construct(
        public string $address,
        public UsageMeter $meter,
        public bool $loginSuspended,
        public bool $incomingSuspended,
    ) {}
}
