<?php

namespace App\Actions\Servers;

use App\Enums\UsageLevel;
use App\Support\ByteSize;

/**
 * One hosting resource at or above the warning threshold (docs/DECISIONS.md T-115): an account
 * resource (disk, traffic, a counter) or one mailbox, or (T-118) a total of a WHM reseller
 * server, which has `serverUsageId`, no `accountUsageId` and no owners. `owners` are the
 * customers whose linked services use the account; a cPanel connection shares its disk and
 * traffic between its domains, so one alert can have several. `mailboxId` is set for a mailbox
 * and `resourceId` for every other resource; `warnedLevel` is what the owner was already
 * e-mailed for.
 */
final readonly class UsageAlert
{
    /**
     * @param  list<array{item_id: int, customer_id: int, short_name: string, domain: string|null}>  $owners
     */
    public function __construct(
        public ?int $accountUsageId,
        public ?string $resourceId,
        public ?int $mailboxId,
        public string $subject,
        public int $used,
        public int $limit,
        public bool $isBytes,
        public UsageLevel $level,
        public ?UsageLevel $warnedLevel,
        public array $owners,
        public ?int $serverUsageId = null,
        public ?int $serverId = null,
        public ?string $serverName = null,
    ) {}

    /**
     * Whether this is a total of a whole reseller server rather than of a customer's account.
     */
    public function isServer(): bool
    {
        return $this->serverUsageId !== null;
    }

    public function percent(): int
    {
        return (int) UsageLevel::percent($this->used, $this->limit);
    }

    /**
     * Used and limit side by side: bytes as "1,2 GB / 5 GB", counts as "10 / 10".
     */
    public function usageText(): string
    {
        return $this->isBytes
            ? ByteSize::format($this->used).' / '.ByteSize::format($this->limit)
            : "{$this->used} / {$this->limit}";
    }

    /**
     * Whether the owner still has to be told: never warned, or the level got worse since.
     */
    public function needsWarning(): bool
    {
        return $this->warnedLevel === null || $this->level->severity() > $this->warnedLevel->severity();
    }
}
