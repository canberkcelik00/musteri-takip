<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ChargeStatus;
use App\Filament\Actions\CancelChargeAction;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\EditChargeAction;
use App\Filament\Actions\SplitChargeAction;
use App\Filament\Concerns\RefreshesOnPaymentOrChargeChange;
use App\Filament\Tables\Columns\ChargeStatusColumn;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use App\Models\Customer;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's charges, newest due date first; only the open ones by default. The remaining
 * amount and the overdue / partially paid state are derived from the allocations, not stored.
 * The "Borç ekle" header action and the row actions (edit, split, cancel) only call the Actions
 * in app/Actions/Charges; the rules live there.
 */
class ChargesRelationManager extends RelationManager
{
    use RefreshesOnPaymentOrChargeChange;

    protected static string $relationship = 'charges';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('charges.plural');
    }

    /**
     * Filament makes relation managers read-only on view pages; the charge actions live on the customer card.
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
            ->recordTitleAttribute('description')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum('allocations', 'amount')
                ->orderByDesc('due_date')
                ->orderByDesc('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('description')
                    ->label(__('charges.description')),
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
            ])
            ->headerActions([
                CreateManualChargeAction::make()->customer($customer)->color('gray'),
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
