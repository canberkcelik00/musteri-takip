<?php

namespace App\Console\Commands;

use App\Actions\Servers\RefreshStaleUsages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:refresh-server-usage')]
#[Description('Refresh the stored usage of hosting accounts that were not refreshed for six hours')]
class RefreshServerUsage extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: the action only reads
     * accounts that are due, within a time budget, and leaves the rest for the next call.
     */
    public function handle(RefreshStaleUsages $refreshStaleUsages): int
    {
        $result = $refreshStaleUsages->handle();

        $this->components->info(__('servers.usage_refresh_result', [
            'refreshed' => $result->refreshed,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
            'removed' => $result->removed,
        ]));

        return self::SUCCESS;
    }
}
