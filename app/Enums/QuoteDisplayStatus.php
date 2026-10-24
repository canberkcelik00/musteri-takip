<?php

namespace App\Enums;

/**
 * The status shown for a quote: the stored status, with "expired" derived from a sent quote
 * whose validity has ended. Never stored (docs/DATA_MODEL.md).
 */
enum QuoteDisplayStatus: string
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
