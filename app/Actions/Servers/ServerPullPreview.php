<?php

namespace App\Actions\Servers;

/**
 * What a pull would do right now, without doing it. `error` is a Turkish reason when the server
 * could not be read; the other fields are then empty.
 */
final readonly class ServerPullPreview
{
    /**
     * @param  list<string>  $newPackages  Packages without a catalog service yet; each needs a price.
     * @param  list<array{key: string, username: string, domain: string, domains: list<string>}>  $newAccountRows  The accounts that would become new customers, with every domain of each (main domain first), for choosing the customer name.
     */
    public function __construct(
        public ?string $error = null,
        public array $newPackages = [],
        public int $newAccounts = 0,
        public int $linkableAccounts = 0,
        public int $alreadyLinkedAccounts = 0,
        public int $suspendedAccounts = 0,
        public array $newAccountRows = [],
    ) {}
}
