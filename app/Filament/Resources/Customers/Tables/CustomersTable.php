<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\Customer;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The customer list (T-66): gecikmiş borçlular üstte, sonra kısa ad (docs/UI.md §5); the row's
 * only action is "Ödeme al" — edit, archive and delete moved to the customer card (T-55) and
 * edit page, per docs/UI.md §2 Kural 6 (satırda en fazla iki metinli işlem, burada bir tane).
 * Responsive (T-68, no horizontal scroll at any width): short name + legal title and the open
 * debt (identity + amount) always show; next renewal from `md`, active service count from `lg`.
 * "Ödeme al" is hidden below `md` too; the row is already clickable to the customer card
 * (`recordUrl()`), the natural place to record a payment below that width.
 */
class CustomersTable
{
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('has_overdue')->orderBy('short_name'))
            ->recordUrl(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => self::withSummaries($query))
            ->columns([
                TextColumn::make('short_name')
                    ->label(__('customers.short_name'))
                    ->weight(FontWeight::Bold)
                    ->description(fn (Customer $record): ?string => $record->legal_title)
                    ->searchable()
                    ->sortable(),
                MoneyColumn::make('open_debt')
                    ->label(__('customers.open_debt'))
                    ->state(fn (Customer $record): array => $record->openChargeTotals())
                    ->color(fn (Customer $record): ?string => $record->has_overdue ? 'danger' : null)
                    ->alignEnd(),
                TextColumn::make('next_renewal_on')
                    ->label(__('customers.next_renewal'))
                    ->date()
                    ->placeholder(__('money.empty'))
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('active_services_count')
                    ->label(__('customers.active_services_count'))
                    ->sortable()
                    ->visibleFrom('lg'),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Customer $record): ?string => $record->isArchived() ? __('archive.status_archived') : null)
                    ->visibleFrom('md'),
            ])
            ->filters([
                ArchivedFilter::make(),
                Filter::make('renewal_soon')
                    ->label(__('customers.renewal_soon'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['renewalSoon'])),
                Filter::make('awaiting_free_decision')
                    ->label(__('customers.awaiting_free_decision'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['awaitingFreeDecision'])),
            ])
            ->recordActions([
                RecordPaymentAction::make()
                    ->color('gray')
                    ->visible(fn (Customer $record): bool => ! $record->isArchived())
                    ->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ]);
    }

    /**
     * Summary values of the list, loaded with the customers so the list does not query per row:
     * active service count, nearest end date of the active services (free ones included), the
     * open charges with their allocated amount, and whether any open charge is overdue (for the
     * sort order and the open debt's color, docs/UI.md §5).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private static function withSummaries(Builder $query): Builder
    {
        $activeServices = fn (Builder $services): Builder => $services->where('status', CustomerServiceStatus::Active);

        return $query
            ->withCount(['customerServices as active_services_count' => $activeServices])
            ->withMin(['customerServices as next_renewal_on' => $activeServices], 'end_date')
            ->withExists(['charges as has_overdue' => fn (Builder $charges): Builder => $charges->scopes(['overdue'])])
            ->with(['charges' => fn ($charges) => $charges
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')]);
    }
}
