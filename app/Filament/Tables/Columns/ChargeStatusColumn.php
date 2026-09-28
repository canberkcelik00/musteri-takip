<?php

namespace App\Filament\Tables\Columns;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use Filament\Tables\Columns\TextColumn;

/**
 * The charge state badge. Overdue wins over partially paid, which wins over due soon,
 * which wins over the plain status; all are derived from the charge on every render,
 * never stored. An open charge that is none of these (not due soon) shows as plain
 * text, no badge (docs/UI.md §6: color is reserved for the four fixed meanings).
 */
class ChargeStatusColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('charges.status'))
            ->badge(fn (Charge $record): bool => self::statusColor($record) !== null)
            ->state(fn (Charge $record): string => self::statusLabel($record))
            ->color(fn (Charge $record): ?string => self::statusColor($record));
    }

    private static function statusColor(Charge $charge): ?string
    {
        return match (true) {
            $charge->isOverdue() => 'danger',
            $charge->isPartiallyPaid() => 'warning',
            $charge->isDueSoon() => 'warning',
            $charge->status === ChargeStatus::Paid => 'success',
            $charge->status === ChargeStatus::Cancelled => 'gray',
            default => null,
        };
    }

    private static function statusLabel(Charge $charge): string
    {
        if ($charge->isOverdue()) {
            return __('charges.overdue_days', ['count' => $charge->daysOverdue()]);
        }

        if ($charge->isPartiallyPaid()) {
            return __('charges.partially_paid');
        }

        if ($charge->isDueSoon()) {
            return __('charges.due_soon');
        }

        return $charge->status->getLabel();
    }
}
