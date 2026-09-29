<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Actions\Customers\DeleteCustomer;
use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Filament\Actions\ArchiveCustomerAction;
use App\Filament\Actions\DeleteRecordAction;
use App\Filament\Actions\UnarchiveAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Filament\Tables\Filters\ArchivedFilter;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('short_name')
            ->recordUrl(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => self::withSummaries($query))
            ->columns([
                TextColumn::make('short_name')
                    ->label(__('customers.short_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('active_services_count')
                    ->label(__('customers.active_services_count'))
                    ->sortable(),
                MoneyColumn::make('open_debt')
                    ->label(__('customers.open_debt'))
                    ->state(fn (Customer $record): array => $record->openChargeTotals()),
                TextColumn::make('next_renewal_on')
                    ->label(__('customers.next_renewal'))
                    ->date()
                    ->placeholder(__('money.empty'))
                    ->sortable(),
                TextColumn::make('archived_at')
                    ->label(__('archive.status'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Customer $record): ?string => $record->isArchived() ? __('archive.status_archived') : null),
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
                EditAction::make(),
                ArchiveCustomerAction::make(),
                UnarchiveAction::make(),
                DeleteRecordAction::make()->deleteWith(DeleteCustomer::class),
            ]);
    }

    /**
     * Summary values of the list, loaded with the customers so the list does not query per row:
     * whether the customer is in use (Delete is offered only when it is not), active service count, nearest end date of the active services (free ones included) and
     * the open charges with their allocated amount.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private static function withSummaries(Builder $query): Builder
    {
        $activeServices = fn (Builder $services): Builder => $services->where('status', CustomerServiceStatus::Active);

        return $query
            ->withInUse()
            ->withCount(['customerServices as active_services_count' => $activeServices])
            ->withMin(['customerServices as next_renewal_on' => $activeServices], 'end_date')
            ->with(['charges' => fn ($charges) => $charges
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')]);
    }
}
