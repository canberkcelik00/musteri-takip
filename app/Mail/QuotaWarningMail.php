<?php

namespace App\Mail;

use App\Actions\Servers\UsageAlert;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Customers\CustomerResource;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The hosting quota warning (docs/PRD.md "Sunucu kullanımı ve kota uyarıları"): everything that
 * newly crossed the warning threshold, in one e-mail to the notification address. Sent
 * synchronously by SendQuotaWarnings; there is no queue worker on the hosting. Every row links to
 * the customer's card.
 */
class QuotaWarningMail extends Mailable
{
    /**
     * @param  Collection<int, UsageAlert>  $alerts
     */
    public function __construct(
        public readonly Collection $alerts,
        public readonly Carbon $date,
        public readonly int $threshold,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('usage.mail.subject', ['count' => $this->alerts->count(), 'date' => $this->date->format('d.m.Y')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quota-warning',
            text: 'mail.quota-warning-text',
            with: [
                'customerUrl' => fn (int $customerId): string => CustomerResource::getUrl('view', ['record' => $customerId], panel: 'panel'),
                'panelUrl' => Dashboard::getUrl(panel: 'panel'),
            ],
        );
    }
}
