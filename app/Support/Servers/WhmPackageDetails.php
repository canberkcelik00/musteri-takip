<?php

namespace App\Support\Servers;

use App\Models\Server;

/**
 * Maps a WHM package onto the `details` of a hosting catalog service (docs/DATA_MODEL.md).
 * WHM reports an unlimited limit as "unlimited" or, for quota and bandwidth, 0.
 */
class WhmPackageDetails
{
    /**
     * @return array{provider: string, disk_space: string, monthly_traffic: string, email_accounts: string, databases: string, sites: string}
     */
    public static function for(RemotePackage $package, Server $server): array
    {
        return [
            'provider' => $server->name,
            'disk_space' => self::megabytes($package->quota),
            'monthly_traffic' => self::megabytes($package->bwlimit),
            'email_accounts' => self::count($package->maxpop),
            'databases' => self::count($package->maxsql),
            'sites' => self::sites($package->maxaddon),
        ];
    }

    private static function megabytes(string $value): string
    {
        return self::isUnlimited($value) ? self::unlimited() : "{$value} MB";
    }

    private static function count(string $value): string
    {
        return self::isUnlimited($value) ? self::unlimited() : $value;
    }

    private static function sites(string $maxAddon): string
    {
        return ctype_digit($maxAddon) ? (string) ((int) $maxAddon + 1) : self::unlimited();
    }

    private static function isUnlimited(string $value): bool
    {
        $value = strtolower(trim($value));

        return ! ctype_digit($value) || (int) $value === 0;
    }

    private static function unlimited(): string
    {
        return __('servers.unlimited');
    }
}
