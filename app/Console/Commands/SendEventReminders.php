<?php

namespace App\Console\Commands;

use App\Actions\Calendar\SendEventReminders as SendEventRemindersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-event-reminders')]
#[Description('Send the calendar event reminder e-mails that are due and not sent yet')]
class SendEventReminders extends Command
{
    /**
     * Execute the console command. Safe to run on every scheduler call: an event day is reminded once.
     */
    public function handle(SendEventRemindersAction $sendEventReminders): int
    {
        $result = $sendEventReminders->handle();

        $this->components->info(__('calendar.reminder_command.summary', $result));

        if ($result['failed'] > 0) {
            $this->components->error(__('calendar.reminder_command.failed', ['count' => $result['failed']]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
