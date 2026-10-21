<?php

namespace App\Actions\Servers;

use App\Exceptions\RemoteServerError;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Support\Servers\ServerApiClient;
use Illuminate\Support\Carbon;

/**
 * Read the totals of a whole WHM reseller from its server and store them (docs/DECISIONS.md
 * T-118). Never throws for a server problem: a failed read keeps the earlier values and records
 * when and why it failed, so callers read `failed_at` / `failure_reason` of the returned row.
 */
class RefreshServerTotals
{
    public function handle(Server $server): ServerUsage
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();

        try {
            $totals = ServerApiClient::for($server)->resellerTotals();
        } catch (RemoteServerError $error) {
            return ServerUsage::query()->updateOrCreate(
                ['server_id' => $server->getKey()],
                ['failed_at' => $now, 'failure_reason' => $error->reason],
            );
        }

        return ServerUsage::query()->updateOrCreate(
            ['server_id' => $server->getKey()],
            [
                'resources' => $totals->toResources(),
                'accounts' => $totals->toAccounts(),
                'refreshed_at' => $now,
                'failed_at' => null,
                'failure_reason' => null,
            ],
        );
    }
}
