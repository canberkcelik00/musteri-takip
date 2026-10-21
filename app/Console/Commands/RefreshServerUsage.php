<?php

namespace App\Console\Commands;

use App\Actions\Servers\RefreshStaleUsages;
use App\Actions\Servers\SendQuotaWarnings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:refresh-server-usage')]
#[Description('Refresh the stored usage of hosting accounts that were not refreshed for the configured interval, then e-mail new quota warnings')]
class RefreshServerUsage extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: the refresh only reads
     * accounts that are due, within a time budget, and leaves the rest for the next call; the
     * warnings only announce what was not announced yet.
     */
    public function handle(RefreshStaleUsages $refreshStaleUsages, SendQuotaWarnings $sendQuotaWarnings): int
    {
        $result = $refreshStaleUsages->handle();

        $this->components->info(__('servers.usage_refresh_result', [
            'refreshed' => $result->refreshed,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
            'removed' => $result->removed,
        ]));

        $sent = $sendQuotaWarnings->handle();

        if ($sent > 0) {
            $this->components->info(__('usage.warnings_sent', ['count' => $sent]));
        }

        return self::SUCCESS;
    }
}
