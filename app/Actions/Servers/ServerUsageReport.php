<?php

namespace App\Actions\Servers;

use App\Enums\RemoteServerErrorReason;
use Illuminate\Support\Carbon;

/**
 * What the server "Kullanım" window shows for one WHM server (docs/UI.md "Sunucu kullanım
 * penceresi"): the reseller totals as gauges, counts, how much the accounts' own limits add up
 * to against the reseller's limit, and every account of the server with its customers.
 * `limitsRead` is false when the server did not give the reseller limits. `allocated*` are the
 * sums of the accounts' limits and `unlimited*Accounts` how many accounts have none.
 */
final readonly class ServerUsageReport
{
    /**
     * @param  list<UsageMeter>  $gauges
     * @param  list<ServerAccountRow>  $accounts
     */
    public function __construct(
        public string $serverName,
        public string $hostname,
        public ?Carbon $refreshedAt,
        public ?Carbon $failedAt,
        public ?RemoteServerErrorReason $failureReason,
        public array $gauges,
        public bool $limitsRead,
        public array $accounts,
        public int $activeCount,
        public int $suspendedCount,
        public int $linkedCount,
        public int $unlinkedCount,
        public int $allocatedDisk,
        public int $unlimitedDiskAccounts,
        public ?int $resellerDiskLimit,
        public int $allocatedBandwidth,
        public int $unlimitedBandwidthAccounts,
        public ?int $resellerBandwidthLimit,
    ) {}

    public function hasData(): bool
    {
        return $this->refreshedAt !== null;
    }
}
