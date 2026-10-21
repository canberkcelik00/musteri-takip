<?php

namespace App\Support\Servers;

/**
 * What a WHM reseller uses across all its accounts: disk, bandwidth and number of accounts, with
 * the reseller's limits. Bytes are bytes, a null limit means unlimited. `limitsRead` says whether
 * the limits could be read at all (the totals can always be summed from the account list, the
 * limits need `resellerstats` / `acctcounts`); a limit that was not read is unknown, not unlimited.
 */
final readonly class RemoteResellerTotals
{
    public function __construct(
        public int $diskBytes,
        public ?int $diskLimitBytes,
        public int $bandwidthBytes,
        public ?int $bandwidthLimitBytes,
        public bool $transferLimitsRead,
        public int $accounts,
        public ?int $accountsLimit,
        public bool $accountsLimitRead,
    ) {}

    /**
     * The stored form: one entry per resource, like an account's resources plus `limit_read`.
     *
     * @return list<array{id: string, label: string, used: int, limit: int|null, is_bytes: bool, limit_read: bool}>
     */
    public function toResources(): array
    {
        return [
            ['id' => 'reseller_disk', 'label' => 'Reseller Disk', 'used' => $this->diskBytes, 'limit' => $this->diskLimitBytes, 'is_bytes' => true, 'limit_read' => $this->transferLimitsRead],
            ['id' => 'reseller_bandwidth', 'label' => 'Reseller Bandwidth', 'used' => $this->bandwidthBytes, 'limit' => $this->bandwidthLimitBytes, 'is_bytes' => true, 'limit_read' => $this->transferLimitsRead],
            ['id' => 'reseller_accounts', 'label' => 'Reseller Accounts', 'used' => $this->accounts, 'limit' => $this->accountsLimit, 'is_bytes' => false, 'limit_read' => $this->accountsLimitRead],
        ];
    }
}
