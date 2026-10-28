<?php

namespace App\Console\Commands;

use App\Actions\Domains\CheckSslCertificates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-ssl')]
#[Description('Read the SSL certificate expiry of the domains of active services, for the domains not checked for 24 hours')]
class CheckSsl extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: only domains that are
     * due are read, within a time budget, and the rest waits for the next call.
     */
    public function handle(CheckSslCertificates $checkSslCertificates): int
    {
        $result = $checkSslCertificates->handle();

        $this->components->info(__('domains.ssl_check_result', [
            'checked' => $result->checked,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
        ]));

        return self::SUCCESS;
    }
}
