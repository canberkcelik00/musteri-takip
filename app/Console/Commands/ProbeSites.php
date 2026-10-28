<?php

namespace App\Console\Commands;

use App\Actions\Domains\ProbeSites as ProbeSitesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:probe-sites')]
#[Description('Ask the sites of active hosting items whether they answer, for the domains not probed for 14 minutes')]
class ProbeSites extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: only domains that are
     * due are asked, within a time budget, and the rest waits for the next call.
     */
    public function handle(ProbeSitesAction $probeSites): int
    {
        $result = $probeSites->handle();

        $this->components->info(__('domains.sites_probe_result', [
            'checked' => $result->checked,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
        ]));

        return self::SUCCESS;
    }
}
