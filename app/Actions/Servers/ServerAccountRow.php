<?php

namespace App\Actions\Servers;

use App\Enums\UsageLevel;

/**
 * One account of a reseller in the server usage window: its disk and bandwidth against the
 * account's own limits, and the customers whose services are linked to it (none: the account is
 * not linked to any customer). `bandwidth` is null when the server did not tell it.
 */
final readonly class ServerAccountRow
{
    /**
     * @param  list<array{id: int, short_name: string, archived: bool}>  $customers
     */
    public function __construct(
        public string $username,
        public string $domain,
        public ?string $plan,
        public bool $suspended,
        public ?string $suspendReason,
        public UsageMeter $disk,
        public ?UsageMeter $bandwidth,
        public array $customers,
    ) {}

    /**
     * The worse of the disk and bandwidth levels; null when both are below the threshold.
     */
    public function level(): ?UsageLevel
    {
        $levels = array_filter([$this->disk->level, $this->bandwidth?->level]);

        return $levels === [] ? null : collect($levels)->sortByDesc(fn (UsageLevel $level): int => $level->severity())->first();
    }
}
