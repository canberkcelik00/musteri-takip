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
