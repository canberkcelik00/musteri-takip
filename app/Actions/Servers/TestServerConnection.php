<?php

namespace App\Actions\Servers;

use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Support\Servers\ServerApiClient;

class TestServerConnection
{
    /**
     * Make one read-only request that needs a valid token and report whether the server
     * accepted it. Never throws: every failure becomes a Turkish message.
     */
    public function handle(Server $server): ServerConnectionResult
    {
        try {
            ServerApiClient::for($server)->ping();
        } catch (RemoteServerError $error) {
            return new ServerConnectionResult(false, $error->getMessage());
        }

        return new ServerConnectionResult(true, __('servers.connection_ok'));
    }
}
