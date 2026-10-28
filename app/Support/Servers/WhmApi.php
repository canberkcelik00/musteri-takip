<?php

namespace App\Support\Servers;

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use Carbon\CarbonImmutable;

class WhmApi extends ServerApiClient
{
    public function ping(): void
    {
        $this->request('json-api/version?api.version=1');
    }

    public function packages(): array
    {
        $rows = $this->request('json-api/listpkgs?api.version=1')['data']['pkg'] ?? null;

        if (! is_array($rows)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return array_values(array_map(fn (array $row): RemotePackage => new RemotePackage(
            name: (string) $row['name'],
            quota: (string) ($row['QUOTA'] ?? '0'),
            bwlimit: (string) ($row['BWLIMIT'] ?? '0'),
            maxpop: (string) ($row['MAXPOP'] ?? '0'),
            maxsql: (string) ($row['MAXSQL'] ?? '0'),
            maxaddon: (string) ($row['MAXADDON'] ?? '0'),
        ), array_filter($rows, fn (mixed $row): bool => is_array($row) && isset($row['name']))));
    }

    public function accounts(): array
    {
        $rows = $this->request('json-api/listaccts?api.version=1')['data']['acct'] ?? null;

        if (! is_array($rows)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return array_values(array_map(fn (array $row): RemoteAccount => new RemoteAccount(
            key: (string) $row['user'],
            domain: (string) ($row['domain'] ?? ''),
            username: (string) $row['user'],
            email: ($row['email'] ?? '') !== '' && $row['email'] !== '*unknown*' ? (string) $row['email'] : null,
            packageName: ($row['plan'] ?? '') !== '' ? (string) $row['plan'] : null,
            startDate: isset($row['unix_startdate']) ? CarbonImmutable::createFromTimestamp((int) $row['unix_startdate'], config('app.timezone'))->startOfDay() : null,
            suspended: (int) ($row['suspended'] ?? 0) === 1,
        ), array_filter($rows, fn (mixed $row): bool => is_array($row) && isset($row['user']))));
    }

    public function siteAddresses(): array
    {
        $rows = $this->request('json-api/listaccts?api.version=1')['data']['acct'] ?? null;

        if (! is_array($rows)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return self::publicAddresses($rows);
    }

    public function domainsOf(RemoteAccount $account): array
    {
        $query = http_build_query([
            'api.version' => 1,
            'cpanel.user' => $account->username,
            'cpanel.module' => 'DomainInfo',
            'cpanel.function' => 'list_domains',
        ]);

        $uapi = $this->request("json-api/uapi_cpanel?{$query}")['data']['uapi'] ?? null;
        $data = is_array($uapi) && ($uapi['status'] ?? null) === 1 ? ($uapi['data'] ?? null) : null;

        if (! is_array($data)) {
            return [$account->domain];
        }

        $domains = array_filter(
            array_map('strval', [
                ...(array) ($data['main_domain'] ?? $account->domain),
                ...(array) ($data['addon_domains'] ?? []),
                ...(array) ($data['parked_domains'] ?? []),
                ...(array) ($data['sub_domains'] ?? []),
            ]),
            fn (string $domain): bool => $domain !== '',
        );

        return array_values(array_unique($domains)) ?: [$account->domain];
    }

    protected function accountDomains(RemoteAccount $account): ?array
    {
        try {
            $data = $this->uapiData('DomainInfo', 'list_domains', $account);
        } catch (RemoteServerError) {
            return null;
        }

        if (! is_array($data) || ! isset($data['main_domain'])) {
            return null;
        }

        $domains = array_map(
            fn (string $domain): string => mb_strtolower($domain),
            array_filter(
                array_map('strval', [...(array) $data['main_domain'], ...(array) ($data['addon_domains'] ?? []), ...(array) ($data['parked_domains'] ?? [])]),
                fn (string $domain): bool => $domain !== '',
            ),
        );

        return array_values(array_unique($domains));
    }

    /**
     * Disk, bandwidth and account counts of the reseller with their limits, and one row per
     * account. `resellerstats` and `acctcounts` give the limits but may be closed to a reseller
     * token ("Access denied" with `metadata.result` 0); then the totals are summed from
     * `listaccts` and `showbw`, which a reseller may always read, and the limits stay unknown. The
     * account rows come from those two calls on every read; an unreadable `showbw` leaves the
     * accounts' bandwidth unknown. A connection or authorization failure is never retried.
     */
    public function resellerTotals(): RemoteResellerTotals
    {
        $user = urlencode($this->server->username);
        $stats = $this->optionalRequest("json-api/resellerstats?api.version=1&user={$user}")['data']['reseller'] ?? null;
        $counts = $this->optionalRequest("json-api/acctcounts?api.version=1&user={$user}")['data']['reseller'] ?? null;
        $rows = $this->optionalRequest('json-api/listaccts?api.version=1')['data']['acct'] ?? null;
        $bandwidth = $this->optionalRequest('json-api/showbw?api.version=1')['data']['acct'] ?? null;

        $statsRead = is_array($stats) && isset($stats['diskused'], $stats['totalbwused']);
        $countsRead = is_array($counts) && isset($counts['active']);
        $rows = is_array($rows) ? array_values(array_filter($rows, fn (mixed $row): bool => is_array($row) && isset($row['user']))) : null;
        $bandwidthByUser = is_array($bandwidth) ? self::bandwidthByUser($bandwidth) : null;

        if ((! $statsRead && ($rows === null || $bandwidthByUser === null)) || (! $countsRead && $rows === null)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        $accounts = array_map(fn (array $row): RemoteServerAccount => self::accountOf($row, $bandwidthByUser), $rows ?? []);

        return new RemoteResellerTotals(
            diskBytes: $statsRead ? self::megabytes($stats['diskused']) : array_sum(array_map(fn (RemoteServerAccount $account): int => $account->diskBytes, $accounts)),
            diskLimitBytes: $statsRead ? self::megabyteLimit($stats['diskquota'] ?? null) : null,
            bandwidthBytes: $statsRead ? self::megabytes($stats['totalbwused']) : array_sum(array_column($bandwidthByUser, 'bytes')),
            bandwidthLimitBytes: $statsRead ? self::megabyteLimit($stats['bandwidthlimit'] ?? null) : null,
            transferLimitsRead: $statsRead,
            accounts: $countsRead ? (int) $counts['active'] + (int) ($counts['suspended'] ?? 0) : count($accounts),
            accountsLimit: $countsRead && (int) ($counts['limit'] ?? 0) > 0 ? (int) $counts['limit'] : null,
            accountsLimitRead: $countsRead,
            accountRows: $accounts,
        );
    }

    /**
     * The body of a call that is allowed to be refused: null when the server answered with an
     * API error or an unexpected body, an exception for everything else (unreachable, 401/403).
     *
     * @return array<string, mixed>|null
     *
     * @throws RemoteServerError
     */
    private function optionalRequest(string $path): ?array
    {
        try {
            return $this->request($path);
        } catch (RemoteServerError $error) {
            if (in_array($error->reason, [RemoteServerErrorReason::ApiError, RemoteServerErrorReason::InvalidResponse], true)) {
                return null;
            }

            throw $error;
        }
    }

    /**
     * Bytes used and limit (null: unlimited) of every account in a `showbw` answer, by user.
     *
     * @param  array<int|string, mixed>  $rows
     * @return array<string, array{bytes: int, limit: int|null}>
     */
    private static function bandwidthByUser(array $rows): array
    {
        $byUser = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['user'])) {
                continue;
            }

            $limit = (int) ($row['unlimited'] ?? 0) === 1 || ! is_numeric($row['limit'] ?? null) || (int) $row['limit'] <= 0 ? null : (int) $row['limit'];
            $byUser[(string) $row['user']] = ['bytes' => (int) ($row['totalbytes'] ?? 0), 'limit' => $limit];
        }

        return $byUser;
    }

    /**
     * @param  array<string, mixed>  $row  a `listaccts` account
     * @param  array<string, array{bytes: int, limit: int|null}>|null  $bandwidthByUser  null when `showbw` could not be read
     */
    private static function accountOf(array $row, ?array $bandwidthByUser): RemoteServerAccount
    {
        $user = (string) $row['user'];
        $diskLimit = self::sizeToBytes((string) ($row['disklimit'] ?? '0'));
        $reason = trim((string) ($row['suspendreason'] ?? ''));

        return new RemoteServerAccount(
            username: $user,
            domain: (string) ($row['domain'] ?? ''),
            plan: ($row['plan'] ?? '') !== '' ? (string) $row['plan'] : null,
            suspended: (int) ($row['suspended'] ?? 0) === 1,
            suspendReason: $reason !== '' && $reason !== 'not suspended' ? $reason : null,
            diskBytes: self::sizeToBytes((string) ($row['diskused'] ?? '0')),
            diskLimitBytes: $diskLimit > 0 ? $diskLimit : null,
            bandwidthBytes: $bandwidthByUser === null ? null : ($bandwidthByUser[$user]['bytes'] ?? 0),
            bandwidthLimitBytes: $bandwidthByUser[$user]['limit'] ?? null,
        );
    }

    /**
     * Megabytes (cPanel's MB are 1024 * 1024 bytes) to bytes; a number or a numeric string.
     */
    private static function megabytes(mixed $value): int
    {
        return is_numeric($value) ? (int) round((float) $value * 1048576) : 0;
    }

    /**
     * A disk or bandwidth limit in megabytes; 0, empty and "unlimited" mean no limit.
     */
    private static function megabyteLimit(mixed $value): ?int
    {
        return is_numeric($value) && (float) $value > 0 ? self::megabytes($value) : null;
    }

    /**
     * "245M", "1.5G" or "0" as `listaccts` reports a disk use, to bytes.
     */
    private static function sizeToBytes(string $size): int
    {
        if (preg_match('/^\s*([\d.]+)\s*([KMGT]?)/i', $size, $match) !== 1) {
            return 0;
        }

        $power = ['' => 2, 'K' => 1, 'M' => 2, 'G' => 3, 'T' => 4][strtoupper($match[2])];

        return (int) round((float) $match[1] * 1024 ** $power);
    }

    protected function uapiData(string $module, string $function, RemoteAccount $account, array $arguments = []): mixed
    {
        $query = http_build_query([
            'api.version' => 1,
            'cpanel.user' => $account->username,
            'cpanel.module' => $module,
            'cpanel.function' => $function,
            ...$arguments,
        ]);

        $uapi = $this->request("json-api/uapi_cpanel?{$query}")['data']['uapi'] ?? null;

        if (! is_array($uapi) || ! array_key_exists('status', $uapi)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        if ((int) $uapi['status'] !== 1) {
            throw RemoteServerError::because(RemoteServerErrorReason::ApiError);
        }

        return $uapi['data'] ?? null;
    }

    protected function authorizationScheme(): string
    {
        return 'whm';
    }

    protected function reportsSuccess(array $body): bool
    {
        return ($body['metadata']['result'] ?? null) === 1;
    }

    protected function hasStatusField(array $body): bool
    {
        return isset($body['metadata']['result']);
    }
}
