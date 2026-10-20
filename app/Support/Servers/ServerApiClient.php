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

    /**
     * @param  array<string, mixed>  $body
     */
    abstract protected function hasStatusField(array $body): bool;
}
