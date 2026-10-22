<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Mail\QuoteMail;
use App\Models\Quote;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendQuote
{
    public function __construct(
        private RenderQuotePdf $renderPdf,
        private Settings $settings,
    ) {}

    /**
     * The address a quote goes to unless another is given: the primary contact's e-mail for a
     * customer, the written e-mail for a hand-written recipient; null when there is none.
     */
    public static function defaultRecipient(Quote $quote): ?string
    {
        $quote->loadMissing('customer.primaryContact');

        $email = $quote->customer !== null
            ? $quote->customer->primaryContact?->email
            : $quote->recipient_email;

        return $email === null || $email === '' ? null : $email;
    }

    /**
     * E-mail the quote with its PDF attached and record the send: a draft becomes "sent", a sent
     * quote (also an expired one) is sent again with the same content. Validity starts again
     * from today when the quote is re-sent, or when a first send finds it already past, so a
     * quote never goes out expired. The copy goes to the quote BCC address, else the notification
     * address, and replies to the notification address. The send is synchronous (no queue worker
     * on the hosting); when it fails the exception propagates and nothing is recorded.
     *
     * @throws InvalidQuote
     */
    public function handle(Quote $quote, ?string $recipient = null): Quote
    {
        $quote->load(['customer.primaryContact', 'items']);

        if (! in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Sent], true)) {
            throw InvalidQuote::notSendable();
        }

        if ($quote->items->isEmpty()) {
            throw InvalidQuote::itemRequired();
        }

        if ($quote->customer?->isArchived()) {
            throw InvalidQuote::customerArchived();
        }

        $recipient = trim($recipient ?? '') !== '' ? trim((string) $recipient) : self::defaultRecipient($quote);

        if ($recipient === null) {
            throw InvalidQuote::noRecipient();
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidQuote::recipientEmailInvalid();
        }

        $today = Carbon::today(config('app.timezone'));
        $validUntil = $quote->status === QuoteStatus::Sent || $quote->valid_until->lt($today)
            ? $today->copy()->addDays($this->settings->quoteValidityDays())
            : $quote->valid_until;

        $notificationEmail = $this->settings->notificationEmail();
        $bcc = $this->settings->quoteBccEmail() ?? $notificationEmail;

        // The PDF and the mail show the validity they go out with; it is saved only after the
        // send, so a failed send leaves the model as it was.
        $originalValidUntil = $quote->valid_until;
        $quote->valid_until = $validUntil;

        try {
            Mail::to($recipient)->send(new QuoteMail(
                $quote,
                $this->renderPdf->handle($quote),
                $this->renderPdf->filename($quote),
                $notificationEmail,
                strcasecmp((string) $bcc, $recipient) === 0 ? null : $bcc,
            ));
        } catch (Throwable $exception) {
            $quote->forceFill(['valid_until' => $originalValidUntil])->syncOriginalAttribute('valid_until');

            throw $exception;
        }

        // A quote accepted or rejected while the mail went out keeps its status.
        Quote::query()
            ->whereKey($quote->getKey())
            ->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent])
            ->update([
                'status' => QuoteStatus::Sent,
                'valid_until' => $validUntil->toDateString(),
                'sent_at' => now(),
                'sent_to' => $recipient,
            ]);

        return $quote->refresh();
    }
}
