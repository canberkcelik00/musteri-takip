<?php

namespace App\Mail;

use App\Models\Quote;
use App\Support\Money;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The quote e-mail with the quote PDF attached (G-6.4). Sent synchronously; there is no queue
 * worker on the hosting. The quote is loaded with `items` and `customer.primaryContact`.
 */
class QuoteMail extends Mailable
{
    public function __construct(
        public readonly Quote $quote,
        public readonly string $pdf,
        public readonly string $filename,
        public readonly ?string $replyToAddress = null,
        public readonly ?string $bccAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            bcc: $this->bccAddress === null ? [] : [new Address($this->bccAddress)],
            replyTo: $this->replyToAddress === null ? [] : [new Address($this->replyToAddress)],
            subject: __('quotes.mail.subject', ['number' => $this->quote->number]),
        );
    }

    public function content(): Content
    {
        $totals = $this->quote->totals();

        return new Content(
            markdown: 'mail.quote',
            text: 'mail.quote-text',
            with: [
                'contactName' => $this->quote->customer?->primaryContact->name ?? $this->quote->recipient_name,
                'number' => $this->quote->number,
                'validUntil' => $this->quote->valid_until,
                'total' => $totals === [] ? __('quotes.pdf.free') : Money::formatTotals($totals),
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }
}
