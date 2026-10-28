<?php

namespace App\Console\Commands;

use App\Actions\Domains\CheckRegistrations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-registration')]
#[Description('Read the registration end and the registrar of the domains of active domain services, for the domains not checked for 24 hours')]
class CheckRegistration extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: only domains that are
     * due are read, within a time budget, and the rest waits for the next call.
     */
    public function handle(CheckRegistrations $checkRegistrations): int
    {
        $result = $checkRegistrations->handle();

        $this->components->info(__('domains.registration_check_result', [
            'checked' => $result->checked,
            'failed' => $result->failed,
            'remaining' => $result->remaining,
        ]));

        return self::SUCCESS;
    }
}
