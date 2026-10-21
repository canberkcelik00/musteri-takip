<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How close a hosting resource is to its limit, from the one warning threshold (docs/UI.md
 * "Kullanım penceresi"): below the threshold there is no level, from the threshold up to the limit
 * it is nearly full, at or over the limit it is full. A resource without a limit has no level.
 * The level is derived on every call, never stored.
 */
enum UsageLevel: string implements HasColor, HasLabel
{
    case NearlyFull = 'nearly_full';
    case Full = 'full';

    /**
     * Integer arithmetic only, so 89.9 % is never rounded up to the 90 % threshold.
     */
    public static function of(int $used, ?int $limit, int $thresholdPercent): ?self
    {
        if ($limit === null || $limit <= 0) {
            return null;
        }

        return match (true) {
            $used >= $limit => self::Full,
            $used * 100 >= $limit * $thresholdPercent => self::NearlyFull,
            default => null,
        };
    }

    /**
     * Whole percent of the limit used, rounded down; null without a limit.
     */
    public static function percent(int $used, ?int $limit): ?int
    {
        return $limit === null || $limit <= 0 ? null : intdiv($used * 100, $limit);
    }

    public function severity(): int
    {
        return match ($this) {
            self::NearlyFull => 1,
            self::Full => 2,
        };
    }

    public function getLabel(): string
    {
        return __('enums.usage_level.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NearlyFull => 'warning',
            self::Full => 'danger',
        };
    }
}
