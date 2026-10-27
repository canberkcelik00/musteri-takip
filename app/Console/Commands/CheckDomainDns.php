<?php

namespace App\Console\Commands;

use App\Actions\Domains\CheckCloudflareDomains;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-domain-dns')]
#[Description('Check which domains of active services point to Cloudflare, for the domains not checked for 24 hours')]
class CheckDomainDns extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: only domains that are
     * due are looked up, within a time budget, and the rest waits for the next call.
     */
    public function handle(CheckCloudflareDomains $checkCloudflareDomains): int
    {
        $result = $checkCloudflareDomains->handle();

        $this->components->info(__('domains.check_result', [
            'checked' => $result->checked,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
        ]));

        return self::SUCCESS;
    }
}
