<?php

namespace App\Console\Commands;

use App\Actions\Deploy\DeployRelease;
use App\Enums\DeployResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:deploy')]
#[Description('Prepare the uploaded release (migrations, caches) when it has not been deployed yet')]
class RunDeploy extends Command
{
    /**
     * Execute the console command. A manual alternative to the deploy address, so a
     * missing release or an already deployed one is not an error.
     */
    public function handle(DeployRelease $deployRelease): int
    {
        try {
            $result = $deployRelease->handle();
        } catch (Throwable) {
            $this->components->error(__('deploy.failed'));

            return self::FAILURE;
        }

        match ($result) {
            DeployResult::Deployed => $this->components->info($result->message()),
            default => $this->components->warn($result->message()),
        };

        return self::SUCCESS;
    }
}
