<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The stored status of a quote. "Expired" is not stored; see QuoteDisplayStatus.
 */
enum QuoteStatus: string implements HasLabel
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
