<?php

namespace App\Filament\Tables\Columns;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use Filament\Tables\Columns\TextColumn;

/**
 * The charge state badge. Overdue wins over partially paid, which wins over the plain status;
 * all three are derived from the charge on every render, never stored.
 */
class ChargeStatusColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.status'))
            ->badge()
            ->state(fn (Charge $record): string => self::statusLabel($record))
            ->color(fn (Charge $record): string => match (true) {
                $record->isOverdue() => 'danger',
                $record->isPartiallyPaid() => 'warning',
                $record->status === ChargeStatus::Paid => 'success',
                $record->status === ChargeStatus::Cancelled => 'gray',
                default => 'info',
            });
    }

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
