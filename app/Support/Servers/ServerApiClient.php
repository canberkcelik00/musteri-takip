<?php

namespace App\Support\Servers;

use App\Enums\RemoteServerErrorReason;
use App\Enums\ServerType;
use App\Exceptions\RemoteServerError;
use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP plumbing of the WHM and cPanel clients: header, timeout, and turning every failure
 * into a `RemoteServerError`.
 */
abstract class ServerApiClient implements RemoteServerApi
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(protected readonly Server $server) {}

    public static function for(Server $server): RemoteServerApi
    {
        return $server->type === ServerType::Whm ? new WhmApi($server) : new CpanelApi($server);
    }

    /**
     * Whether the decoded body reports success (WHM `metadata.result`, UAPI `status`).
     *
     * @param  array<string, mixed>  $body
     */
    abstract protected function reportsSuccess(array $body): bool;

    abstract protected function authorizationScheme(): string;

    /**
     * @return array<string, mixed>
     *
     * @throws RemoteServerError
     */
    protected function request(string $path): array
    {
        $url = "https://{$this->server->hostname}:{$this->server->port}/{$path}";

        try {
            $response = Http::withHeaders(['Authorization' => "{$this->authorizationScheme()} {$this->server->username}:{$this->server->api_token}"])
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $exception) {
            throw RemoteServerError::because(
                str_contains($exception->getMessage(), 'cURL error 28')
                    ? RemoteServerErrorReason::Timeout
                    : RemoteServerErrorReason::ConnectionFailed,
            );
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw RemoteServerError::because(RemoteServerErrorReason::Unauthorized);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        if (! $this->reportsSuccess($body)) {
            throw RemoteServerError::because(
                $this->hasStatusField($body) ? RemoteServerErrorReason::ApiError : RemoteServerErrorReason::InvalidResponse,
            );
        }

        return $body;
    }

    public function usageOf(RemoteAccount $account): RemoteAccountUsage
    {
        $resources = $this->uapiRows($this->uapiData('ResourceUsage', 'get_usages', $account));
        $mailboxes = $this->uapiRows($this->uapiData('Email', 'list_pops_with_disk', $account, ['get_restrictions' => 1]));
        $databases = $this->uapiRows($this->uapiData('Mysql', 'list_databases', $account));

        return new RemoteAccountUsage(
            resources: array_values(array_map(RemoteResource::fromUapi(...), array_filter($resources, fn (array $row): bool => isset($row['id'])))),
            mailboxes: array_values(array_map(RemoteMailbox::fromUapi(...), array_filter($mailboxes, fn (array $row): bool => isset($row['email']) || isset($row['login'])))),
            databases: array_values(array_map(RemoteDatabase::fromUapi(...), array_filter($databases, fn (array $row): bool => isset($row['database'])))),
            domains: $this->accountDomains($account),
        );
    }

    /**
     * Every `ip` value found anywhere in an API answer that is a public IPv4 address, each once.
     *
     * @return list<string>
     */
    protected static function publicAddresses(mixed $data): array
    {
        $addresses = [];

        array_walk_recursive($data, function (mixed $value, int|string $key) use (&$addresses): void {
            if ($key === 'ip' && is_string($value) && filter_var(trim($value), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                $addresses[] = trim($value);
            }
        });

        return array_values(array_unique($addresses));
    }

    /**
     * Main, addon and parked domains of the account, lower case, main first (no sub domains);
     * null when this kind of connection does not give them or the call failed, so the usage
     * read never fails for the domains.
     *
     * @return list<string>|null
     */
    abstract protected function accountDomains(RemoteAccount $account): ?array;

    /**
     * The `data` of one UAPI call made for the account.
     *
     * @param  array<string, scalar>  $arguments
     *
     * @throws RemoteServerError
     */
    abstract protected function uapiData(string $module, string $function, RemoteAccount $account, array $arguments = []): mixed;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws RemoteServerError
     */
    private function uapiRows(mixed $data): array
    {
        if (! is_array($data)) {
            throw RemoteServerError::because(RemoteServerErrorReason::InvalidResponse);
        }

        return array_values(array_filter($data, is_array(...)));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    abstract protected function hasStatusField(array $body): bool;
}
