<?php

namespace App\Actions\Servers;

/**
 * What a pull did. `error` is a Turkish reason when the server could not be read; nothing was
 * imported then and the other results are null.
 */
final readonly class ServerPullResult
{
    public function __construct(
        public ?string $error = null,
        public ?PackageImportResult $packages = null,
        public ?AccountImportResult $accounts = null,
    ) {}
}
