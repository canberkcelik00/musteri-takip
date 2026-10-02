<?php

namespace App\Filament\Support;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * A charge's state under its "Kalan" amount in both charge tables (docs/UI.md §5, §6; G-4.17):
 * the state used to be a column of its own, whose width pushed the row buttons ("Ödeme al",
 * "Düzenle") out of the card at 1280 px. Overdue wins over partially paid, which wins over due
 * soon, which wins over the plain status; all are derived from the charge on every render, never
 * stored. Color is reserved for the four fixed meanings: an open charge that is none of them (not
 * due soon) has no badge, and shows its plain "Açık" text only where the list asks for it.
 */
final class ChargeStatusBadge
{
    public static function color(Charge $charge): ?string
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

    public static function label(Charge $charge): string
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

    /**
     * The colored badge, or — for a plain open charge — the plain state text when
     * `$showPlainStatus` is set (the list); null when there is nothing to show (the card).
     */
    public static function html(Charge $charge, bool $showPlainStatus = false): ?Htmlable
    {
        $color = self::color($charge);

        if ($color === null) {
            return $showPlainStatus ? new HtmlString(e(self::label($charge))) : null;
        }

        return new HtmlString(View::make('filament.support.charge-status-badge', [
            'label' => self::label($charge),
            'color' => $color,
        ])->render());
    }

    /**
     * The badge for narrow screens, under "Kalan" while the "Durum" column is hidden (below `md`);
     * null for a plain open charge, which has no colored state.
     */
    public static function mobileHtml(Charge $charge): ?Htmlable
    {
        $color = self::color($charge);

        return $color === null ? null : MobileStatusBadge::html(self::label($charge), $color);
    }
}
