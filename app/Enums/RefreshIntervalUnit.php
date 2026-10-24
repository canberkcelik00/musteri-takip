<?php

namespace App\Enums;

/**
 * The unit of the usage refresh interval on the settings page. Only minutes are stored
 * (SettingKey::UsageRefreshMinutes); the unit is derived when the form opens.
 */
enum RefreshIntervalUnit: string
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';

    public function minutes(int $value): int
    {
        return $value * $this->minutesPerUnit();
    }

    /**
     * The largest unit that divides the minutes evenly: 1440 is 1 day, 120 is 2 hours, 90 is
     * 90 minutes.
     *
     * @return array{int, self}
     */
    public static function split(int $minutes): array
    {
        foreach ([self::Day, self::Hour] as $unit) {
            if ($minutes > 0 && $minutes % $unit->minutesPerUnit() === 0) {
                return [intdiv($minutes, $unit->minutesPerUnit()), $unit];
            }
        }

        return [$minutes, self::Minute];
    }

    public function getLabel(): string
    {
        return __('enums.refresh_interval_unit.'.$this->value);
    }

    private function minutesPerUnit(): int
    {
        return match ($this) {
            self::Minute => 1,
            self::Hour => 60,
            self::Day => 1440,
        };
    }
}
