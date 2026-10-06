<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Actions\DeletePaymentAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Tables\Columns\MoneyColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The payment list. Responsive like the card's "Son ödemeler" (T-68, G-4B.7, no horizontal
 * scroll from 375 px): customer, date and amount always show; the customer wraps, the payment
 * method shows from `md` and the note from `lg`. Below `md` the row's "Sil" is hidden and the
 * amount opens the same confirmation instead, as on the card's "Son ödemeler" (T-69); the
 * date does not wrap, so the delete column would not fit a phone.
 */
class PaymentsTable
{
    private const string HIDDEN_BELOW_MD = 'fi-hidden-below-md';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'paymentMethod']))
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('paid_on')->orderByDesc('id'))
            ->columns([
                TextColumn::make('customer.short_name')
                    ->label(__('payments.customer'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('paid_on')
                    ->label(__('payments.paid_on'))
                    ->date(),
                MoneyColumn::make('amount')
                    ->label(__('payments.amount'))
                    ->action(DeletePaymentAction::make('deletePaymentMobile')),
                TextColumn::make('paymentMethod.name')
                    ->label(__('payments.payment_method'))
                    ->visibleFrom('md'),
                TextColumn::make('note')
                    ->label(__('payments.note'))
                    ->placeholder(__('money.empty'))
                    ->wrap()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->label(__('payments.customer'))
                    ->relationship('customer', 'short_name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                DeletePaymentAction::make()->extraAttributes(['class' => self::HIDDEN_BELOW_MD]),
            ])
            ->emptyStateHeading(__('payments.empty_heading'))
            ->emptyStateDescription(__('payments.empty_description'))
            ->emptyStateActions([
                RecordPaymentAction::make('recordPaymentEmpty')->color('gray'),
            ]);
    }
}
