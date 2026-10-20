<?php

namespace App\Actions\Servers;

use App\Enums\ServerType;
use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TestServerConnection
{
    private const int TIMEOUT_SECONDS = 10;

    /**
     * Make one read-only request that needs a valid token and report whether the server
     * accepted it. Never throws: every failure becomes a Turkish message.
     */
    public function handle(Server $server): ServerConnectionResult
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->authorization($server)])
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($this->url($server));
        } catch (ConnectionException $exception) {
            return $this->failure(str_contains($exception->getMessage(), 'cURL error 28') ? 'timeout' : 'failed');
        }

        if (in_array($response->status(), [401, 403], true)) {
            return $this->failure('unauthorized');
        }

        if (! $response->successful() || ! $this->reportsSuccess($server, $response)) {
            return $this->failure('invalid_response');
        }

        return new ServerConnectionResult(true, __('servers.connection_ok'));
    }

    private function authorization(Server $server): string
    {
        $scheme = $server->type === ServerType::Whm ? 'whm' : 'cpanel';

        return "{$scheme} {$server->username}:{$server->api_token}";
    }

    private function url(Server $server): string
    {
        $path = $server->type === ServerType::Whm
            ? 'json-api/version?api.version=1'
            : 'execute/Variables/get_user_information';

        return "https://{$server->hostname}:{$server->port}/{$path}";
    }

    private function reportsSuccess(Server $server, Response $response): bool
    {
        return $server->type === ServerType::Whm
            ? $response->json('metadata.result') === 1
            : $response->json('status') === 1;
    }

    private function failure(string $reason): ServerConnectionResult
    {
        return new ServerConnectionResult(false, __("servers.connection_{$reason}"));
    }
}
