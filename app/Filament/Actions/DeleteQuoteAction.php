<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\DeleteQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

/**
 * Deletes a draft quote with its items through DeleteQuote after confirmation; a quote that was
 * sent is kept for good, so the action is only offered for drafts.
 */
class DeleteQuoteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('quotes.delete'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('quotes.delete_heading'))
            ->modalDescription(__('quotes.delete_description'))
            ->modalSubmitActionLabel(__('quotes.delete'))
            ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Draft)
            ->action(function (Quote $record, Action $action, DeleteQuote $deleteQuote, Component $livewire): void {
                try {
                    $deleteQuote->handle($record);
                } catch (InvalidQuote $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();
                    $action->halt();

                    return;
                }

                $action->successNotificationTitle(__('quotes.deleted'))->success();
                $livewire->dispatch(CreateQuoteAction::QUOTES_CHANGED_EVENT);
            });
    }
}
