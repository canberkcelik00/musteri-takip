<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Actions\DeletePaymentAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Customer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's payments, newest first. "Ödeme al" records a payment for the customer and
 * the row action deletes one; both only call the Actions in app/Actions/Payments. The header
 * button is gray, not primary, since the topbar's "Ödeme al" is the screen's one primary
 * action (docs/UI.md §4).
 */
class PaymentsRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('payments.plural');
    }

    /**
     * Filament makes relation managers read-only on view pages; the record and delete actions live on the customer card.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $customer = $this->getOwnerRecord();
        assert($customer instanceof Customer);

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
            ])
            ->headerActions([
                RecordPaymentAction::make()->customer($customer)->color('gray'),
            ])
            ->recordActions([
                DeletePaymentAction::make(),
            ]);
    }
}
