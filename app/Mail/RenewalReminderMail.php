<?php

namespace App\Mail;

use App\Models\Charge;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * The renewal reminder sent to a customer's primary contact (G-5B.1). Sent synchronously; there is
 * no queue worker on the hosting. The charge is loaded with `customer.primaryContact`,
 * `customerService` and the allocations sum, so the remaining amount needs no extra query.
 */
class RenewalReminderMail extends Mailable
{
    public function __construct(
        public readonly Charge $charge,
        public readonly ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->replyToAddress === null ? [] : [new Address($this->replyToAddress)],
            subject: __('reminders.subject', ['service' => $this->charge->customerService->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.renewal-reminder',
            text: 'mail.renewal-reminder-text',
            with: [
                'contactName' => $this->charge->customer->primaryContact?->name,
                'serviceName' => $this->charge->customerService->name,
                'endDate' => $this->charge->period_start,
                'hasEnded' => $this->charge->period_start->lt(Carbon::today(config('app.timezone'))),
                'periodEnd' => $this->charge->period_end,
                'dueDate' => $this->charge->due_date,
                'remaining' => $this->charge->remainingAmount(),
            ],
        );
    }
}
