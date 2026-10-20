<?php

namespace App\Actions\Servers;

/**
 * What an account pull did, by the account's remote key (WHM: cPanel username, cPanel: domain).
 */
final readonly class AccountImportResult
{
    /**
     * @param  list<string>  $created  New customer service made (a new customer too, unless the short name existed).
     * @param  list<string>  $linked  An existing hosting item was connected to the account.
     * @param  list<string>  $alreadyLinked
     * @param  list<string>  $suspendedSkipped
     * @param  list<string>  $missingOnServer  Linked on our side but no longer listed by the server.
     * @param  array<string, string>  $errors  Remote key => reason.
     */
    public function __construct(
        public int $customersCreated = 0,
        public array $created = [],
        public array $linked = [],
        public array $alreadyLinked = [],
        public array $suspendedSkipped = [],
        public array $missingOnServer = [],
        public array $errors = [],
    ) {}
}
