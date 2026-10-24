<?php

namespace App\Support\Ui;

use App\Enums\ChargeStatus;
use App\Enums\QuoteDisplayStatus;
use App\Models\Charge;
use App\Models\Quote;

/**
 * Status badges as the glass interface draws them (docs/UI.md §5, §6): a tone (red, amber, green, gray) and a text.
 * Derived on every render from the charge or quote, never stored; a plain state has no tone and no badge.
 */
final class StatusBadges
{
    /**
     * A charge's state: overdue wins over partially paid, which wins over due soon, which wins over the plain status.
     */
    public static function chargeTone(Charge $charge): ?string
    {
        return match (true) {
            $charge->isOverdue() => 'red',
            $charge->isPartiallyPaid(), $charge->isDueSoon() => 'amber',
            $charge->status === ChargeStatus::Paid => 'green',
            $charge->status === ChargeStatus::Cancelled => 'gray',
            default => null,
        };
    }

    public static function chargeLabel(Charge $charge): string
    {
        return match (true) {
            $charge->isOverdue() => __('charges.overdue_days', ['count' => $charge->daysOverdue()]),
            $charge->isPartiallyPaid() => __('charges.partially_paid'),
            $charge->isDueSoon() => __('charges.due_soon'),
            default => $charge->status->getLabel(),
        };
    }

    /**
     * Gray is the passive state (draft, rejected), amber the one that needs a decision (expired), green accepted; a
     * sent, still valid quote is plain text.
     */
    public static function quoteTone(Quote $quote): ?string
    {
        return match ($quote->displayStatus()) {
            QuoteDisplayStatus::Draft, QuoteDisplayStatus::Rejected => 'gray',
            QuoteDisplayStatus::Expired => 'amber',
            QuoteDisplayStatus::Accepted => 'green',
            QuoteDisplayStatus::Sent => null,
        };
    }

    public static function quoteLabel(Quote $quote): string
    {
        return $quote->displayStatus()->getLabel();
    }

    /**
     * The tone a Filament-era color name stands for (usage levels still name theirs that way).
     */
    public static function toneOfColor(?string $color): string
    {
        return match ($color) {
            'danger' => 'red',
            'warning' => 'amber',
            'success' => 'green',
            default => 'gray',
        };
    }
}
