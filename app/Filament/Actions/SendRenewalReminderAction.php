<?php

namespace App\Filament\Actions;

use App\Actions\Reminders\SendRenewalReminder;
use App\Actions\Reminders\SendRenewalReminders;
use App\Exceptions\ReminderNotSent;
use App\Models\Charge;
use App\Providers\AppServiceProvider;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * Sends the renewal reminder of an eligible charge by hand through SendRenewalReminder (G-5B.2),
 * also again for a charge that was reminded before. The confirmation names the recipient; without a
 * valid address it only explains why nothing can be sent. A failed send is logged and shown
 * without details (an SMTP error may carry credentials); nothing is recorded.
 */
class SendRenewalReminderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendRenewalReminder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('reminders.send'))
            ->icon(Heroicon::Envelope)
            ->requiresConfirmation()
            ->modalHeading(__('reminders.send_heading'))
            ->modalSubmitActionLabel(__('reminders.send'))
            ->modalDescription(function (Charge $record): string {
                $recipient = SendRenewalReminders::recipientFor($record);
                $text = $recipient === null
                    ? __('reminders.no_recipient')
                    : __('reminders.send_confirm', ['email' => $recipient]);

                if ($record->reminder_sent_at !== null) {
                    $text .= ' '.__('reminders.last_sent', ['date' => $record->reminder_sent_at->format(AppServiceProvider::DATE_FORMAT.' H:i')]);
                }

                return $text;
            })
            ->modalSubmitAction(fn (Charge $record): ?bool => SendRenewalReminders::recipientFor($record) === null ? false : null)
            ->visible(fn (Charge $record, SendRenewalReminder $sendRenewalReminder): bool => $sendRenewalReminder->isEligible($record))
            ->action(function (Charge $record, Action $action, SendRenewalReminder $sendRenewalReminder, Component $livewire): void {
                try {
                    $sendRenewalReminder->handle($record);
                } catch (ReminderNotSent $exception) {
                    $action->failureNotificationTitle($exception->getMessage())->failure();

                    return;
                } catch (Throwable $exception) {
                    Log::error('Renewal reminder could not be sent.', [
                        'charge_id' => $record->id,
                        'customer_id' => $record->customer_id,
                        'exception' => $exception,
                    ]);

                    $action->failureNotificationTitle(__('reminders.send_failed'))->failure();

                    return;
                }

                $action->successNotificationTitle(__('reminders.sent_notification'))->success();

                $livewire->dispatch(CreateManualChargeAction::CHARGES_CHANGED_EVENT);
            });
    }
}
