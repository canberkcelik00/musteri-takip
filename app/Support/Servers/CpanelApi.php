<?php

namespace App\Support\Servers;

use App\Enums\RemoteServerErrorReason;
use App\Exceptions\RemoteServerError;
use LogicException;

/**
 * cPanel exposes no packages; each main or addon domain of the account becomes one RemoteAccount.
 */
class CpanelApi extends ServerApiClient
{
    public function ping(): void
    {
        $this->request('execute/Variables/get_user_information');
    }

    public function packages(): array
    {
        return [];
    }

    public function accounts(): array
    {
        $data = $this->request('execute/DomainInfo/list_domains')['data'] ?? null;

        if (! is_array($data) || ! isset($data['main_domain'])) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        $domains = array_unique([(string) $data['main_domain'], ...array_map('strval', (array) ($data['addon_domains'] ?? []))]);

        return array_map(fn (string $domain): RemoteAccount => new RemoteAccount(
            key: $domain,
            domain: $domain,
            username: $this->server->username,
            email: null,
            packageName: null,
            startDate: null,
            suspended: false,
        ), array_values($domains));
    }

    public function domainsOf(RemoteAccount $account): array
    {
        return [$account->domain];
    }

    protected function accountDomains(RemoteAccount $account): ?array
    {
        return null;
    }

    public function resellerTotals(): RemoteResellerTotals
    {
        throw new LogicException('A cPanel connection has no reseller totals.');
    }

    protected function uapiData(string $module, string $function, RemoteAccount $account, array $arguments = []): mixed
    {
        $query = $arguments === [] ? '' : '?'.http_build_query($arguments);

        return $this->request("execute/{$module}/{$function}{$query}")['data'] ?? null;
    }

    protected function authorizationScheme(): string
    {
        return 'cpanel';
    }

    protected function reportsSuccess(array $body): bool
    {
        return ($body['status'] ?? null) === 1;
    }

    protected function hasStatusField(array $body): bool
    {
        return isset($body['status']);
    }
}
