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

    /**
     * Disk, bandwidth and account counts of the reseller with their limits. `resellerstats` and
     * `acctcounts` give the limits but may be closed to a reseller token ("Access denied" with
     * `metadata.result` 0); then the totals are summed from `listaccts` and `showbw`, which a
     * reseller may always read, and the limits stay unknown. A connection or authorization
     * failure is never retried that way.
     */
    public function resellerTotals(): RemoteResellerTotals
    {
        $user = urlencode($this->server->username);
        $stats = $this->optionalRequest("json-api/resellerstats?api.version=1&user={$user}")['data']['reseller'] ?? null;
        $counts = $this->optionalRequest("json-api/acctcounts?api.version=1&user={$user}")['data']['reseller'] ?? null;

        $statsRead = is_array($stats) && isset($stats['diskused'], $stats['totalbwused']);
        $countsRead = is_array($counts) && isset($counts['active']);

        $accounts = $statsRead && $countsRead ? [] : $this->listAccountRows();

        if ($statsRead) {
            $diskBytes = self::megabytes($stats['diskused']);
            $bandwidthBytes = self::megabytes($stats['totalbwused']);
        } else {
            $diskBytes = array_sum(array_map(fn (array $row): int => self::sizeToBytes((string) ($row['diskused'] ?? '0')), $accounts));
            $bandwidthBytes = $this->bandwidthOfAccounts();
        }

        return new RemoteResellerTotals(
            diskBytes: $diskBytes,
            diskLimitBytes: $statsRead ? self::megabyteLimit($stats['diskquota'] ?? null) : null,
            bandwidthBytes: $bandwidthBytes,
            bandwidthLimitBytes: $statsRead ? self::megabyteLimit($stats['bandwidthlimit'] ?? null) : null,
            transferLimitsRead: $statsRead,
            accounts: $countsRead ? (int) $counts['active'] + (int) ($counts['suspended'] ?? 0) : count($accounts),
            accountsLimit: $countsRead && (int) ($counts['limit'] ?? 0) > 0 ? (int) $counts['limit'] : null,
            accountsLimitRead: $countsRead,
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
     * @return list<array<string, mixed>>
     *
     * @throws RemoteServerError
     */
    private function listAccountRows(): array
    {
        $rows = $this->request('json-api/listaccts?api.version=1')['data']['acct'] ?? null;

        if (! is_array($rows)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return array_values(array_filter($rows, is_array(...)));
    }

    /**
     * @throws RemoteServerError
     */
    private function bandwidthOfAccounts(): int
    {
        $rows = $this->request('json-api/showbw?api.version=1')['data']['acct'] ?? null;

        if (! is_array($rows)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return array_sum(array_map(fn (mixed $row): int => is_array($row) ? (int) ($row['totalbytes'] ?? 0) : 0, $rows));
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
