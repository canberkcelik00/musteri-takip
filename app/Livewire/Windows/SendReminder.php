<?php

namespace App\Livewire\Windows;

use App\Actions\Reminders\SendRenewalReminder;
use App\Actions\Reminders\SendRenewalReminders;
use App\Exceptions\ReminderNotSent;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Charge;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Sends the renewal reminder of an eligible charge by hand through SendRenewalReminder (G-5B.2), also again for a
 * charge that was reminded before. The window names the recipient; without a valid address it only explains why
 * nothing can be sent. A failed send is logged and shown without details (an SMTP error may carry credentials).
 */
class SendReminder extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $chargeId = 0;

    public function mount(int $charge): void
    {
        $this->chargeId = Charge::query()->findOrFail($charge)->id;
    }

    public function send(SendRenewalReminder $sendRenewalReminder): void
    {
        $charge = Charge::query()->findOrFail($this->chargeId);

        try {
            $sendRenewalReminder->handle($charge);
        } catch (ReminderNotSent $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::error('Renewal reminder could not be sent.', [
                'charge_id' => $charge->id,
                'customer_id' => $charge->customer_id,
                'exception' => $exception,
            ]);

            $this->addError('form', __('reminders.send_failed'));

            return;
        }

        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('reminders.sent_notification'));
    }

    public function render(): View
    {
        $charge = Charge::query()->findOrFail($this->chargeId);
        $recipient = SendRenewalReminders::recipientFor($charge);
        $text = $recipient === null
            ? __('reminders.no_recipient')
            : __('reminders.send_confirm', ['email' => $recipient]);

        if ($charge->reminder_sent_at !== null) {
            $text .= ' '.__('reminders.last_sent', ['date' => $charge->reminder_sent_at->format('d.m.Y H:i')]);
        }

        return view('livewire.windows.send-reminder', ['text' => $text, 'canSend' => $recipient !== null]);
    }
}
