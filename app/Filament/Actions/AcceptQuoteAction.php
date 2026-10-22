<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\AcceptQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Filament\Support\FieldError;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Livewire\Component;

/**
 * Accepts a sent, still valid quote through AcceptQuote. The window asks the start date (today)
 * and, for a hand-written recipient, the short name of the customer that is created (the company,
 * else the person's name); a refusal appears under the field it is about. Everything is created
 * together or not at all.
 */
class AcceptQuoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'acceptQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('quotes.accept'))
            ->modalHeading(__('quotes.accept_heading'))
            ->modalDescription(__('quotes.accept_helper'))
            ->modalSubmitActionLabel(__('quotes.accept'))
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Sent && ! $record->isExpired())
            ->fillForm(fn (Quote $record): array => [
                'start_date' => today()->toDateString(),
                'short_name' => $record->customer_id === null
                    ? ($record->recipient_company ?: $record->recipient_name)
                    : null,
            ])
            ->schema([
                DatePicker::make('start_date')
                    ->label(__('quotes.start_date'))
                    ->required(),
                TextInput::make('short_name')
                    ->label(__('quotes.short_name'))
                    ->helperText(__('quotes.short_name_helper'))
                    ->required()
                    ->maxLength(255)
                    ->visible(fn (): bool => $this->quote()->customer_id === null),
            ])
            ->action(function (array $data, Quote $record, Action $action, AcceptQuote $acceptQuote, Component $livewire): void {
                try {
                    $accepted = $acceptQuote->handle($record, $data['start_date'], $data['short_name'] ?? null);
                } catch (InvalidQuote|InvalidCustomerService $exception) {
                    FieldError::report($exception, $action, ['start_date', 'short_name']);

                    return;
                }

                $action->successNotificationTitle(__('quotes.accepted', ['count' => $accepted->items->count()]))->success();
                $livewire->dispatch(CreateQuoteAction::QUOTES_CHANGED_EVENT);
                $livewire->dispatch(AssignCustomerServiceAction::SERVICES_CHANGED_EVENT);
                $livewire->dispatch(EditChargeAction::CHARGES_CHANGED_EVENT);
            });
    }

    private function quote(): Quote
    {
        $record = $this->getRecord();
        assert($record instanceof Quote);

        return $record;
    }
}
