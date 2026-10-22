<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\SendQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Filament\Support\FieldError;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

/**
 * E-mails the quote with its PDF through SendQuote: "Gönder" for a draft, "Tekrar gönder" for a
 * quote that was sent before (also an expired one, which becomes valid again). The one field is
 * the recipient's address, prefilled with the quote's own. A failed send is logged and shown
 * without details (an SMTP error may carry credentials); nothing is recorded.
 */
class SendQuoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Quote $record): string => $this->isResend($record) ? __('quotes.resend') : __('quotes.send'))
            ->icon(Heroicon::Envelope)
            ->modalHeading(fn (Quote $record): string => $this->isResend($record) ? __('quotes.resend_heading') : __('quotes.send_heading'))
            ->modalSubmitActionLabel(fn (Quote $record): string => $this->isResend($record) ? __('quotes.resend') : __('quotes.send'))
            ->visible(fn (Quote $record): bool => in_array($record->status, [QuoteStatus::Draft, QuoteStatus::Sent], true))
            ->fillForm(fn (Quote $record): array => ['recipient_email' => SendQuote::defaultRecipient($record)])
            ->schema([
                TextInput::make('recipient_email')
                    ->label(__('quotes.send_recipient'))
                    ->helperText(fn (): string => $this->isResend($this->quote()) ? __('quotes.resend_helper') : __('quotes.send_helper'))
                    ->email()
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data, Quote $record, Action $action, SendQuote $sendQuote, Component $livewire): void {
                try {
                    $sent = $sendQuote->handle($record, $data['recipient_email']);
                } catch (InvalidQuote $exception) {
                    FieldError::report($exception, $action, ['recipient_email']);

                    return;
                } catch (Throwable $exception) {
                    Log::error('Quote could not be sent.', [
                        'quote_id' => $record->id,
                        'exception' => $exception,
                    ]);

                    $action->failureNotificationTitle(__('quotes.send_failed'))->failure();

                    return;
                }

                $action->successNotificationTitle(__('quotes.sent', ['email' => $sent->sent_to]))->success();
                $livewire->dispatch(CreateQuoteAction::QUOTES_CHANGED_EVENT);
            });
    }

    private function quote(): Quote
    {
        $record = $this->getRecord();
        assert($record instanceof Quote);

        return $record;
    }

    private function isResend(Quote $quote): bool
    {
        return $quote->status !== QuoteStatus::Draft;
    }
}
