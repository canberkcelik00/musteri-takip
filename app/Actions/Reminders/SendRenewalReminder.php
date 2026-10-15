<?php

namespace App\Actions\Reminders;

use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\ReminderNotSent;
use App\Mail\RenewalReminderMail;
use App\Models\Charge;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

/**
 * Send the renewal reminder of one charge and record it (G-5B.2). Shared by the scheduled run
 * ({@see SendRenewalReminders}) and the manual "Hatırlatma gönder" action. Independent of the
 * "Müşteriye yenileme hatırlatması gönder" setting, which only governs the scheduled run, and of
 * the due date and `reminder_sent_at`: a manual send may repeat a reminder or go out for an
 * overdue charge. The send is synchronous (no queue worker on the hosting); when it fails the
 * exception propagates and nothing is recorded.
 */
class SendRenewalReminder
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * An open renewal charge of an active, non-free service of an unarchived customer.
     */
    public function isEligible(Charge $charge): bool
    {
        if (! $charge->is_renewal || $charge->status !== ChargeStatus::Open) {
            return false;
        }

        $charge->loadMissing(['customer', 'customerService']);
        $service = $charge->customerService;

        return $service !== null
            && $service->status === CustomerServiceStatus::Active
            && ! $service->is_free
            && ! $charge->customer->isArchived();
    }

    /**
     * @throws ReminderNotSent When the charge is not eligible or has no valid recipient address.
     */
    public function handle(Charge $charge): void
    {
        $charge->loadMissing(['customer.primaryContact', 'customerService']);

        if (! $this->isEligible($charge)) {
            throw ReminderNotSent::notEligible();
        }

        $recipient = SendRenewalReminders::recipientFor($charge) ?? throw ReminderNotSent::noRecipient();

        $charge->loadSum('allocations', 'amount');

        Mail::to($recipient)->send(new RenewalReminderMail($charge, $this->settings->notificationEmail()));

        $charge->forceFill(['reminder_sent_at' => now()])->save();
    }
}
