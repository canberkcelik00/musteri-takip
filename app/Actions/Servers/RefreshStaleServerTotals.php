<?php

namespace App\Actions\Servers;

use App\Enums\ServerType;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Refresh the reseller totals of every active WHM server that was not asked for the configured
 * interval ("henüz yapılmadıysa yap"; docs/DECISIONS.md T-118), the same interval and the same
 * rule as the account usage: a failed attempt counts as an attempt. There are few servers, so
 * unlike {@see RefreshStaleUsages} there is no time budget. Totals of archived and cPanel servers
 * are removed.
 */
class RefreshStaleServerTotals
{
    public function __construct(
        private readonly RefreshServerTotals $refreshServerTotals,
        private readonly Settings $settings,
    ) {}

    public function handle(): ServerTotalsRefreshResult
    {
        $threshold = Carbon::now(config('app.timezone'))->startOfSecond()->subMinutes($this->settings->usageRefreshMinutes());
        $servers = Server::query()->notArchived()->where('type', ServerType::Whm)->with('serverUsage')->get();

        $removed = ServerUsage::query()->whereNotIn('server_id', $servers->modelKeys())->delete();
        $refreshed = 0;
        $failed = 0;

        foreach ($servers as $server) {
            $lastAttempt = $server->serverUsage?->lastAttemptAt();

            if ($lastAttempt !== null && $lastAttempt->greaterThan($threshold)) {
                continue;
            }

            $usage = $this->refreshServerTotals->handle($server);
            $usage->failure_reason === null ? $refreshed++ : $failed++;
        }

        return new ServerTotalsRefreshResult($refreshed, $failed, $removed);
    }
}
