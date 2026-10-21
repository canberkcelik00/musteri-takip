<?php

namespace App\Support\Servers;

/**
 * One account of a WHM reseller as the account list reports it (`listaccts` + `showbw`), for the
 * server usage window: disk and bandwidth in bytes, a null limit meaning unlimited, and null
 * bandwidth when `showbw` could not be read.
 */
final readonly class RemoteServerAccount
{
    public function __construct(
        public string $username,
        public string $domain,
        public ?string $plan,
        public bool $suspended,
        public ?string $suspendReason,
        public int $diskBytes,
        public ?int $diskLimitBytes,
        public ?int $bandwidthBytes,
        public ?int $bandwidthLimitBytes,
    ) {}

    /**
     * The stored form (`server_usages.accounts`).
     *
     * @return array{username: string, domain: string, plan: string|null, suspended: bool, suspend_reason: string|null, disk: int, disk_limit: int|null, bw: int|null, bw_limit: int|null}
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'plan' => $this->plan,
            'suspended' => $this->suspended,
            'suspend_reason' => $this->suspendReason,
            'disk' => $this->diskBytes,
            'disk_limit' => $this->diskLimitBytes,
            'bw' => $this->bandwidthBytes,
            'bw_limit' => $this->bandwidthLimitBytes,
        ];
    }
}
