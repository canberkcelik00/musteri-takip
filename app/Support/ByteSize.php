<?php

namespace App\Support;

/**
 * Formats a byte count for display in Turkish: "512 B", "1,2 GB", "5 GB". Binary units (1 KB = 1024 B),
 * one decimal at most, a trailing ",0" is dropped.
 */
final class ByteSize
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

    public static function format(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $unit = 0;
        $tenths = $bytes * 10;

        while ($tenths >= 10240 && $unit < count(self::UNITS) - 1) {
            $tenths = intdiv($tenths + 512, 1024);
            $unit++;
        }

        if ($unit === 0) {
            return $bytes.' '.self::UNITS[0];
        }

        $whole = intdiv($tenths, 10);
        $fraction = $tenths % 10;

        return ($fraction === 0 ? (string) $whole : "{$whole},{$fraction}").' '.self::UNITS[$unit];
    }
}
