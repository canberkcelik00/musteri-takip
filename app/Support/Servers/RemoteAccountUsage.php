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
     */
    public function __construct(
        public array $resources,
        public array $mailboxes,
        public array $databases,
    ) {}
}
