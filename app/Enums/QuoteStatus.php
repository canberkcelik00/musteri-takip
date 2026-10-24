<?php

namespace App\Enums;

/**
 * The stored status of a quote. "Expired" is not stored; see QuoteDisplayStatus.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('enums.quote_status.'.$this->value);
    }
}
