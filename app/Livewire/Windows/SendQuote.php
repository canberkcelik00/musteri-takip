<?php

namespace App\Livewire\Windows;

use App\Actions\Quotes\SendQuote as SendQuoteAction;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Quote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * E-mails the quote with its PDF through SendQuote: "Gönder" for a draft, "Tekrar gönder" for a quote that was sent
 * before (also an expired one, which becomes valid again). The one field is the recipient's address, prefilled with
 * the quote's own. A failed send is logged and shown without details (an SMTP error may carry credentials).
 */
class SendQuote extends Component
{
    use ReportsDomainErrors;

    #[Locked]
    public int $quoteId = 0;

    public string $recipient_email = '';

    public function mount(int $quote): void
    {
        $record = Quote::query()->findOrFail($quote);

        $this->quoteId = $record->id;
        $this->recipient_email = (string) SendQuoteAction::defaultRecipient($record);
    }

    public function send(SendQuoteAction $sendQuote): void
    {
        $this->validate(['recipient_email' => ['required', 'email', 'max:255']], attributes: ['recipient_email' => __('quotes.send_recipient')]);

        $quote = Quote::query()->findOrFail($this->quoteId);

        try {
            $sent = $sendQuote->handle($quote, $this->recipient_email);
        } catch (InvalidQuote $exception) {
            $this->reportDomainError($exception, ['recipient_email']);

            return;
        } catch (Throwable $exception) {
            Log::error('Quote could not be sent.', [
                'quote_id' => $quote->id,
                'exception' => $exception,
            ]);

            $this->addError('form', __('quotes.send_failed'));

            return;
        }

        $this->dispatch('quotes-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('quotes.sent', ['email' => $sent->sent_to]));
    }

    public function render(): View
    {
        $quote = Quote::query()->findOrFail($this->quoteId);
        $resend = $quote->status !== QuoteStatus::Draft;

        return view('livewire.windows.send-quote', ['resend' => $resend]);
    }
}
