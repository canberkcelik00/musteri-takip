<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Actions\CreateQuoteAction;
use App\Filament\Actions\OpenQuoteAction;
use App\Filament\Support\QuoteStatusBadge;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Customer;
use App\Models\Quote;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * The customer card's "Teklifler" (G-6.7, docs/UI.md §5 Teklifler): the customer's quotes, newest
 * first, with number, total and state. The card shows it only for a customer that has quotes, and
 * it has no button to add one: quotes start from the catalog or the quote list. The number opens
 * the same window as the quote list (PDF indir, Gönder, Kabul et, Reddet, …), so a quote can be
 * accepted from the card; the services and charges it creates show on the card at once (the
 * window's actions tell the page). Narrow: number, total and state fit at 375 px, the total
 * listing one currency per line.
 */
class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('quotes.card_heading');
    }

    /**
     * Filament makes relation managers read-only on view pages; the quote window's actions live here.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * A quote changed through its window; the table just renders again.
     */
    #[On(CreateQuoteAction::QUOTES_CHANGED_EVENT)]
    public function refreshAfterQuoteChange(): void {}

    public function table(Table $table): Table
    {
        $customer = $this->getOwnerRecord();
        assert($customer instanceof Customer);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['customer', 'items'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('number')
                    ->label(__('quotes.column.number'))
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(['class' => 'fi-ta-name-link'])
                    ->action(OpenQuoteAction::make('openQuote')),
                MoneyColumn::make('total')
                    ->label(__('quotes.column.total'))
                    ->state(fn (Quote $record): array => $record->totals())
                    ->placeholder(__('quotes.pdf.free')),
                TextColumn::make('status')
                    ->label(__('quotes.column.status'))
                    ->state(fn (Quote $record): string => QuoteStatusBadge::label($record))
                    ->badge(fn (Quote $record): bool => QuoteStatusBadge::color($record) !== null)
                    ->color(fn (Quote $record): ?string => QuoteStatusBadge::color($record)),
            ])
            ->emptyStateHeading(__('quotes.empty_heading'));
    }
}
