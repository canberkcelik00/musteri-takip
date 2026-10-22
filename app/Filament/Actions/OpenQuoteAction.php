<?php

namespace App\Filament\Actions;

use App\Actions\Quotes\UpdateQuote;
use App\Enums\Currency;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Filament\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\Support\FieldError;
use App\Filament\Support\QuotableItems;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Livewire\Component;

/**
 * A quote's window (docs/UI.md §5 Teklifler). A draft opens as an editable form saved through
 * UpdateQuote; a quote that was sent opens with the same fields locked and its rarer actions
 * (PDF indir, Tekrar gönder, Kabul et, Reddet) below, a draft with PDF indir, Gönder and, apart,
 * Sil. The list names it "openQuote" on the number and "editQuote" on a draft's row button.
 */
class OpenQuoteAction extends EditAction
{
    public static function getDefaultName(): ?string
    {
        return 'openQuote';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('quotes.edit'))
            ->modalHeading(fn (Quote $record): string => __('quotes.edit_heading', ['number' => $record->number]))
            ->modalSubmitAction(fn (Quote $record): ?bool => $record->status === QuoteStatus::Draft ? null : false)
            ->modalCancelActionLabel(fn (Quote $record): string => $record->status === QuoteStatus::Draft ? __('actions.discard') : __('actions.close'))
            ->mutateRecordDataUsing(fn (array $data, Quote $record): array => $this->stateOf($record))
            ->schema(fn (): array => QuoteForm::components())
            ->successNotificationTitle(__('quotes.saved'))
            ->using(function (Quote $record, array $data, Action $action, UpdateQuote $updateQuote, Component $livewire): Quote {
                $customer = filled($data['customer_id'] ?? null) ? Customer::query()->find($data['customer_id']) : null;

                try {
                    $quote = $updateQuote->handle($record, $customer, $data, $this->itemsOf($data));
                } catch (InvalidQuote $exception) {
                    FieldError::report($exception, $action, ['recipient_name', 'recipient_email', 'items']);

                    return $record;
                }

                $livewire->dispatch(CreateQuoteAction::QUOTES_CHANGED_EVENT);

                return $quote;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function stateOf(Quote $quote): array
    {
        return [
            'customer_id' => $quote->customer_id,
            'recipient_name' => $quote->recipient_name,
            'recipient_company' => $quote->recipient_company,
            'recipient_phone' => $quote->recipient_phone,
            'recipient_email' => $quote->recipient_email,
            'notes' => $quote->notes,
            'items' => $quote->items()->get()->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'currency' => $item->currency->value,
                'is_free' => $item->is_free,
            ])->all(),
        ];
    }

    /**
     * The window's lines as UpdateQuote takes them: an existing line by `id`, a new one by the
     * catalog service or package picked.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{id?: int, catalog_service_id?: int, package_id?: int, price?: int, currency?: Currency, is_free?: bool}>
     */
    private function itemsOf(array $data): array
    {
        $items = [];

        foreach ($data['items'] ?? [] as $row) {
            $line = [
                'price' => $row['price'] ?? null,
                'currency' => $row['currency'] instanceof Currency ? $row['currency'] : Currency::from($row['currency']),
                'is_free' => (bool) ($row['is_free'] ?? false),
            ];

            if (filled($row['id'] ?? null)) {
                $items[] = ['id' => (int) $row['id'], ...$line];

                continue;
            }

            $quoted = QuotableItems::resolve($row['item'] ?? null) ?? throw InvalidQuote::invalidItem();

            $items[] = [($quoted instanceof Package ? 'package_id' : 'catalog_service_id') => $quoted->id, ...$line];
        }

        return $items;
    }
}
