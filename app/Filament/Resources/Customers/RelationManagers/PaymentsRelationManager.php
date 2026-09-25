<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Tables\Columns\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's payments, newest first. Read-only; recording and deleting payments comes with G-4.7.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('paymentMethod')
                ->orderByDesc('paid_on')
                ->orderByDesc('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('paid_on')
                    ->label(__('payments.paid_on'))
                    ->date(),
                MoneyColumn::make('amount')
                    ->label(__('payments.amount')),
                TextColumn::make('paymentMethod.name')
                    ->label(__('payments.payment_method')),
                TextColumn::make('note')
                    ->label(__('payments.note'))
                    ->placeholder(__('money.empty')),
            ]);
    }
}
