<?php

namespace App\Filament\Resources\Charges\Tables;

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Filament\Actions\CancelChargeAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\SplitChargeAction;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Tables\Columns\ChargeStatusColumn;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('customer')
                ->withSum('allocations', 'amount'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('due_date')->orderBy('id'))
            ->columns([
                TextColumn::make('customer.short_name')
                    ->label(__('charges.customer'))
                    ->searchable()
                    ->url(fn (Charge $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                TextColumn::make('description')
                    ->label(__('charges.description'))
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label(__('charges.due_date'))
                    ->date(),
                MoneyColumn::make('amount')
                    ->label(__('charges.amount')),
                MoneyColumn::make('remaining')
                    ->label(__('charges.remaining'))
                    ->state(fn (Charge $record): ?int => $record->status === ChargeStatus::Open ? $record->remainingAmount() : null)
                    ->currencyAttribute('currency'),
                ChargeStatusColumn::make('status'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('charges.status'))
                    ->options(ChargeStatus::class)
                    ->default(ChargeStatus::Open->value),
                Filter::make('only_overdue')
                    ->label(__('charges.only_overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['overdue'])),
                Filter::make('due_this_month')
                    ->label(__('charges.due_this_month'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['dueThisMonth'])),
                Filter::make('due_soon')
                    ->label(__('charges.due_soon_filter'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->scopes(['dueSoon'])),
                SelectFilter::make('currency')
                    ->label(__('charges.currency'))
                    ->options(Currency::class),
                SelectFilter::make('customer')
                    ->label(__('charges.customer'))
                    ->relationship('customer', 'short_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditChargeAction::make(),
                ActionGroup::make([
                    SplitChargeAction::make(),
                    CancelChargeAction::make(),
                ]),
            ]);
    }
}
