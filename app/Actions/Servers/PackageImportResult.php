<?php

namespace App\Actions\Servers;

/**
 * What a package pull did, by package name.
 */
final readonly class PackageImportResult
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skippedWithoutPrice
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $skippedWithoutPrice = [],
    ) {}
}
