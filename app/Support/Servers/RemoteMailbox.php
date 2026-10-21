<?php

namespace App\Support\Servers;

/**
 * One e-mail address of a hosting account with its disk use. `quotaBytes` is null when the
 * mailbox has no quota.
 */
final readonly class RemoteMailbox
{
    public function __construct(
        public string $address,
        public string $domain,
        public int $usedBytes,
        public ?int $quotaBytes,
        public bool $loginSuspended,
        public bool $incomingSuspended,
    ) {}

    /**
     * Reads UAPI `Email::list_pops_with_disk`, where `_diskused` and `_diskquota` are bytes and a
     * quota of 0 means unlimited.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromUapi(array $row): self
    {
        $address = (string) ($row['email'] ?? $row['login'] ?? '');
        $quota = $row['_diskquota'] ?? null;

        return new self(
            address: $address,
            domain: (string) ($row['domain'] ?? (str_contains($address, '@') ? substr(strrchr($address, '@'), 1) : '')),
            usedBytes: is_numeric($row['_diskused'] ?? null) ? (int) $row['_diskused'] : 0,
            quotaBytes: is_numeric($quota) && (int) $quota > 0 ? (int) $quota : null,
            loginSuspended: (int) ($row['suspended_login'] ?? 0) === 1,
            incomingSuspended: (int) ($row['suspended_incoming'] ?? 0) === 1,
        );
    }
}
