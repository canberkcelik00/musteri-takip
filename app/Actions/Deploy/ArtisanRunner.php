<?php

namespace App\Actions\Deploy;

use Illuminate\Support\Facades\Artisan;

/**
 * Runs an Artisan command and returns its exit code.
 *
 * A separate class so tests can replace it and check which commands a deploy runs.
 */
class ArtisanRunner
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function run(string $command, array $parameters = []): int
    {
        return Artisan::call($command, $parameters);
    }
}
