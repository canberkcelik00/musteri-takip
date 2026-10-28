<?php

namespace App\Console\Commands;

use App\Actions\Domains\CheckHostingPoints as CheckHostingPointsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-hosting-points')]
#[Description('Check whether the domains of hosting items still point to their server, for the domains not checked for 24 hours')]
class CheckHostingPoints extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: only domains that are
     * due are read, within a time budget, and the rest waits for the next call.
     */
    public function handle(CheckHostingPointsAction $checkHostingPoints): int
    {
        $result = $checkHostingPoints->handle();

        $this->components->info(__('domains.points_check_result', [
            'checked' => $result->checked,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
        ]));

        return self::SUCCESS;
    }
}
