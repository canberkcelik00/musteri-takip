<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\CreateQuote;
use App\Exceptions\InvalidQuote;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Filament\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\Support\FieldError;
use App\Filament\Support\QuotableItems;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Livewire\Component;

/**
 * Starts a draft quote with the catalog service or package of the row it was pressed on, or —
 * from the quote list's "Yeni teklif" — with one picked in the window. The recipient is an
 * existing customer or a person written by hand. It only calls CreateQuote; the draft is then
 * opened in the quote list, where "Düzenle" adds items and "Gönder" sends it (docs/UI.md §5).
 */
class CreateQuoteAction extends Action
{
    public const string QUOTES_CHANGED_EVENT = 'quotes-changed';

    public static function getDefaultName(): ?string
    {
        return 'createQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (): string => $this->quotedRecord() === null ? __('quotes.new') : __('quotes.create'))
            ->modalHeading(__('quotes.create_heading'))
            ->modalSubmitActionLabel(__('actions.save'))
            ->visible(fn (): bool => ! ($this->quotedRecord()?->isArchived() ?? false))
            ->schema([
                Select::make('sold')
                    ->label(__('quotes.sold'))
                    ->options(fn (): array => QuotableItems::options())
                    ->searchable()
                    ->required()
                    ->visible(fn (): bool => $this->quotedRecord() === null)
                    ->columnSpanFull(),
                ...QuoteForm::recipientFields(),
                QuoteForm::notes(),
            ])
            ->action(function (array $data, Action $action, CreateQuote $createQuote, Component $livewire): void {
                $quoted = $this->quotedRecord() ?? QuotableItems::resolve($data['sold'] ?? null);
                assert($quoted !== null);

                $customer = filled($data['customer_id'] ?? null) ? Customer::query()->find($data['customer_id']) : null;

                try {
                    $quote = $createQuote->handle($quoted, $customer, [
                        'recipient_name' => $data['recipient_name'] ?? null,
                        'recipient_company' => $data['recipient_company'] ?? null,
                        'recipient_phone' => $data['recipient_phone'] ?? null,
                        'recipient_email' => $data['recipient_email'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);
                } catch (InvalidQuote $exception) {
                    FieldError::report($exception, $action, ['recipient_name', 'recipient_email']);

                    return;
                }

                $livewire->dispatch(self::QUOTES_CHANGED_EVENT);

                $action
                    ->successNotificationTitle(__('quotes.created', ['number' => $quote->number]))
                    ->successRedirectUrl(QuoteResource::getUrl('index'))
                    ->success();
            });
    }

    /**
     * The catalog service or package of the row; null for the quote list's "Yeni teklif".
     */
    private function quotedRecord(): CatalogService|Package|null
    {
        $record = $this->getRecord();

        return $record instanceof CatalogService || $record instanceof Package ? $record : null;
    }
}
