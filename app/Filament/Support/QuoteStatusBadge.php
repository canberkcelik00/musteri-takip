<?php

namespace App\Filament\Support;

use App\Enums\QuoteDisplayStatus;
use App\Models\Quote;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * A quote's state in the quote list (docs/UI.md §5, §6). It is derived on every render from the
 * stored status and `valid_until`, never stored: a sent quote past its validity shows as expired.
 * Gray is the passive state (draft, rejected), amber the one that needs a decision (expired),
 * green accepted; a sent, still valid quote is plain text without a badge.
 */
final class QuoteStatusBadge
{
    public static function color(Quote $quote): ?string
    {
        return match ($quote->displayStatus()) {
            QuoteDisplayStatus::Draft, QuoteDisplayStatus::Rejected => 'gray',
            QuoteDisplayStatus::Expired => 'warning',
            QuoteDisplayStatus::Accepted => 'success',
            QuoteDisplayStatus::Sent => null,
        };
    }

    public static function label(Quote $quote): string
    {
        return $quote->displayStatus()->getLabel();
    }

    /**
     * The colored badge, or the plain state text for a sent quote that is still valid.
     */
    public static function html(Quote $quote): Htmlable
    {
        $color = self::color($quote);

        if ($color === null) {
            return new HtmlString(e(self::label($quote)));
        }

        return new HtmlString(View::make('filament.support.charge-status-badge', [
            'label' => self::label($quote),
            'color' => $color,
        ])->render());
    }
}
