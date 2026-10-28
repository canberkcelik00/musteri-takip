<?php

namespace App\Mail;

use App\Actions\Servers\UsageAlert;
use App\Enums\DigestFrequency;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\SiteIncident;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * The digest e-mail (daily or monthly). Sent synchronously by SendDigest; there is no queue
 * worker on the hosting. Every row links to the customer's card.
 */
class DigestMail extends Mailable
{
    /**
     * @param  array{new_charges: Collection<int, Charge>, free_decisions: Collection<int, CustomerService>, overdue: Collection<int, Charge>, missing_reminder_email: Collection<int, Charge>, quota_alerts: SupportCollection<int, UsageAlert>, down_sites: Collection<int, SiteIncident>}  $digest
     */
    public function __construct(
        public readonly array $digest,
        public readonly DigestFrequency $frequency,
        public readonly Carbon $date,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('digest.subject.'.$this->frequency->value, ['date' => $this->date->format('d.m.Y')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest',
            text: 'mail.digest-text',
            with: [
                'customerUrl' => fn (int $customerId): string => route('app.customers.show', $customerId),
                'panelUrl' => route('app.today'),
            ],
        );
    }
}
