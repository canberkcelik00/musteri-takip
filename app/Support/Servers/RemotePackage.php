<?php

namespace App\Support\Servers;

/**
 * A WHM hosting package. Limits are kept as WHM reports them: a number or "unlimited".
 */
final readonly class RemotePackage
{
    public function __construct(
        public string $name,
        public string $quota,
        public string $bwlimit,
        public string $maxpop,
        public string $maxsql,
        public string $maxaddon,
    ) {}
}
