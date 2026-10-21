<?php

namespace App\Support\Servers;

/**
 * What a server reports about the use of one hosting account: metered resources, e-mail
 * addresses and databases.
 */
final readonly class RemoteAccountUsage
{
    /**
     * @param  list<RemoteResource>  $resources
     * @param  list<RemoteMailbox>  $mailboxes
     * @param  list<RemoteDatabase>  $databases
     * @param  list<string>|null  $domains  main, addon and parked domains, lower case, main first; null when not read (cPanel connections, or the call failed)
     */
    public function __construct(
        public array $resources,
        public array $mailboxes,
        public array $databases,
        public ?array $domains = null,
    ) {}
}
