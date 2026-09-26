<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Actions\DeletePaymentAction;
use App\Filament\Tables\Columns\MoneyColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'paymentMethod']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('paid_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('paid_on')
                    ->label(__('payments.paid_on'))
                    ->date(),
                TextColumn::make('customer.short_name')
                    ->label(__('payments.customer'))
                    ->searchable(),
                MoneyColumn::make('amount')
                    ->label(__('payments.amount')),
                TextColumn::make('paymentMethod.name')
                    ->label(__('payments.payment_method')),
                TextColumn::make('note')
                    ->label(__('payments.note'))
                    ->placeholder(__('money.empty')),
            ])
            ->recordActions([
                DeletePaymentAction::make(),
            ]);
    }
}
