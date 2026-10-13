<?php

namespace App\Console\Commands;

use App\Actions\Digest\SendDigest as SendDigestAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-digest')]
#[Description('Send the digest e-mail when its scheduled moment has passed and it was not handled yet')]
class SendDigest extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: the action only
     * sends once per scheduled moment.
     */
    public function handle(SendDigestAction $sendDigest): int
    {
        ['result' => $result, 'next_scheduled_at' => $nextScheduledAt] = $sendDigest->handle();

        $this->components->info(__('digest.result.'.$result->value));
        $this->components->info(__('digest.next_scheduled_at', ['moment' => $nextScheduledAt->format('d.m.Y H:i')]));

        return self::SUCCESS;
    }
}
