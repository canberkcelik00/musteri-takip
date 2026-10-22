<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\QuoteDisplayStatus;
use App\Enums\QuoteStatus;
use App\Filament\Actions\AcceptQuoteAction;
use App\Filament\Actions\CreateQuoteAction;
use App\Filament\Actions\OpenQuoteAction;
use App\Filament\Actions\RejectQuoteAction;
use App\Filament\Actions\SendQuoteAction;
use App\Filament\Support\QuoteStatusBadge;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Quote;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The quote list (docs/UI.md §5 Teklifler). Number, total and state always show, the validity from
 * `md`. The number opens the quote's window at every width; below `md` the row's buttons are
 * hidden and the window holds the same actions. A row has at most two labelled buttons: a draft
 * "Düzenle" + "Gönder", a sent quote that is still valid "Kabul et" + "Reddet", an expired one
 * "Tekrar gönder", a decided one none. The state is derived on every render (Quote::displayStatus).
 */
class QuotesTable
{
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'items']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('quotes.column.number'))
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Quote $record): string => self::recipientOf($record))
                    ->extraAttributes(['class' => 'fi-ta-name-link'])
                    ->action(OpenQuoteAction::make('openQuote'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::search($query, $search)),
                MoneyColumn::make('total')
                    ->label(__('quotes.column.total'))
                    ->state(fn (Quote $record): array => $record->totals())
                    ->placeholder(__('quotes.pdf.free'))
                    ->singleLine(),
                TextColumn::make('valid_until')
                    ->label(__('quotes.column.valid_until'))
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->label(__('quotes.column.status'))
                    ->state(fn (Quote $record): string => QuoteStatusBadge::label($record))
                    ->badge(fn (Quote $record): bool => QuoteStatusBadge::color($record) !== null)
                    ->color(fn (Quote $record): ?string => QuoteStatusBadge::color($record)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('quotes.filter_status'))
                    ->options(QuoteDisplayStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => self::filterByStatus($query, $data['value'] ?? null)),
            ])
            ->recordActions([
                OpenQuoteAction::make('editQuote')
                    ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Draft)
                    ->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
                SendQuoteAction::make()
                    ->visible(fn (Quote $record): bool => $record->status === QuoteStatus::Draft || $record->isExpired())
                    ->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
                AcceptQuoteAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
                RejectQuoteAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ])
            ->emptyStateHeading(__('quotes.empty_heading'))
            ->emptyStateDescription(__('quotes.empty_description'))
            ->emptyStateActions([
                CreateQuoteAction::make('createQuoteEmpty')->color('gray'),
            ]);
    }

    /**
     * The recipient under the number: the customer's short name, else the written company and
     * name.
     */
    private static function recipientOf(Quote $quote): string
    {
        if ($quote->customer !== null) {
            return $quote->customer->short_name;
        }

        return implode(' · ', array_filter([$quote->recipient_company, $quote->recipient_name]));
    }

    /**
     * Number, written recipient and customer short name.
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    private static function search(Builder $query, string $search): Builder
    {
        $like = '%'.addcslashes($search, '\\%_').'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('number', 'like', $like)
                ->orWhere('recipient_name', 'like', $like)
                ->orWhere('recipient_company', 'like', $like)
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('short_name', 'like', $like));
        });
    }

    /**
     * The state filter works on the displayed state: "Süresi doldu" is a sent quote past its
     * validity, "Gönderildi" one that is still valid.
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    private static function filterByStatus(Builder $query, ?string $value): Builder
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        return match (QuoteDisplayStatus::tryFrom((string) $value)) {
            QuoteDisplayStatus::Expired => $query->where('status', QuoteStatus::Sent)->where('valid_until', '<', $today),
            QuoteDisplayStatus::Sent => $query->where('status', QuoteStatus::Sent)->where('valid_until', '>=', $today),
            QuoteDisplayStatus::Draft => $query->where('status', QuoteStatus::Draft),
            QuoteDisplayStatus::Accepted => $query->where('status', QuoteStatus::Accepted),
            QuoteDisplayStatus::Rejected => $query->where('status', QuoteStatus::Rejected),
            null => $query,
        };
    }
}
