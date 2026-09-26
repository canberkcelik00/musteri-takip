<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\ChargeStatus;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Tables\Columns\MoneyColumn;
use App\Models\Charge;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * The customer's charges, newest due date first; only the open ones by default. The remaining
 * amount and the overdue / partially paid state are derived from the allocations, not stored.
 * Read-only; editing charges comes with G-4.8.
 */
class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('charges.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Recording or deleting a payment changes the charges' remaining amounts; the empty
     * method is enough, the component renders again when the event arrives.
     */
    #[On(RecordPaymentAction::PAYMENTS_CHANGED_EVENT)]
    public function refreshAfterPaymentChange(): void {}

    public function table(Table $table): Table
    {
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
                TextColumn::make('status')
                    ->label(__('charges.status'))
                    ->badge()
                    ->state(fn (Charge $record): string => self::statusLabel($record))
                    ->color(fn (Charge $record): string => match (true) {
                        $record->isOverdue() => 'danger',
                        $record->isPartiallyPaid() => 'warning',
                        $record->status === ChargeStatus::Paid => 'success',
                        $record->status === ChargeStatus::Cancelled => 'gray',
                        default => 'info',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('charges.status'))
                    ->options(ChargeStatus::class)
                    ->default(ChargeStatus::Open->value),
            ]);
    }

    /**
     * Overdue wins over partially paid, which wins over the plain status.
     */
    private static function statusLabel(Charge $charge): string
    {
        if ($charge->isOverdue()) {
            return __('charges.overdue_days', ['count' => $charge->daysOverdue()]);
        }

        if ($charge->isPartiallyPaid()) {
            return __('charges.partially_paid');
        }

        return $charge->status->getLabel();
    }
}
