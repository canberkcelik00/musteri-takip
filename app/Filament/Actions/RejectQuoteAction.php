<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\RejectQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * Closes a sent, still valid quote as rejected through RejectQuote after confirmation; nothing
 * else is created.
 */
class RejectQuoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rejectQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('quotes.reject'))
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('quotes.reject_heading'))
            ->modalDescription(fn (Quote $record): string => __('quotes.reject_description', ['number' => $record->number]))
            ->modalSubmitActionLabel(__('quotes.reject'))
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Sent && ! $record->isExpired())
            ->action(function (Quote $record, Action $action, RejectQuote $rejectQuote, Component $livewire): void {
                try {
                    $rejectQuote->handle($record);
                } catch (InvalidQuote $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();

                    return;
                }

                $action->successNotificationTitle(__('quotes.rejected'))->success();
                $livewire->dispatch(CreateQuoteAction::QUOTES_CHANGED_EVENT);
            });
    }
}
