<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The status shown for a quote: the stored status, with "expired" derived from a sent quote
 * whose validity has ended. Never stored (docs/DATA_MODEL.md).
 */
enum QuoteDisplayStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('enums.quote_display_status.'.$this->value);
    }
}
