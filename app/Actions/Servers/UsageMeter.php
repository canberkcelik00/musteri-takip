<?php

namespace App\Actions\Servers;

use App\Enums\UsageLevel;
use App\Support\ByteSize;

/**
 * One measured value of a hosting account for display: an account resource (disk, traffic, a
 * counter), a mailbox or a database. `level` comes from the one warning threshold
 * ({@see UsageLevel}); a value without a limit has none and is never warned about. `limitKnown`
 * is false for a limit the server could not tell (reseller totals); the used value then stands alone.
 */
final readonly class UsageMeter
{
    public function __construct(
        public string $id,
        public string $label,
        public int $used,
        public ?int $limit,
        public bool $isBytes,
        public ?UsageLevel $level,
        public bool $limitKnown = true,
    ) {}

    public function percent(): ?int
    {
        return UsageLevel::percent($this->used, $this->limit);
    }

    public function usedText(): string
    {
        return $this->isBytes ? ByteSize::format($this->used) : (string) $this->used;
    }

    /**
     * "1,2 GB / 5 GB", "4 / 10" or, without a limit, "1,2 GB · Limitsiz".
     */
    public function usageText(): string
    {
        if (! $this->limitKnown) {
            return $this->usedText();
        }

        if ($this->limit === null || $this->limit <= 0) {
            return $this->usedText().' · '.__('servers.unlimited');
        }

        return $this->usedText().' / '.($this->isBytes ? ByteSize::format($this->limit) : $this->limit);
    }
}
