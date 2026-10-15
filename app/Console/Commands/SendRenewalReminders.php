<?php

namespace App\Console\Commands;

use App\Actions\Reminders\SendRenewalReminders as SendRenewalRemindersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-renewal-reminders')]
#[Description('Send the renewal reminder e-mails that have not been sent yet')]
class SendRenewalReminders extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: a charge is reminded once.
     */
    public function handle(SendRenewalRemindersAction $sendRenewalReminders): int
    {
        $result = $sendRenewalReminders->handle();

        $this->components->info(__('reminders.result.summary', $result));

        if ($result['failed'] > 0) {
            $this->components->error(__('reminders.command_failed', ['count' => $result['failed']]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
