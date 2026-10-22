<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;

class LockQuoteForDecision
{
    /**
     * Lock the quote's row and check that it can still be accepted or rejected: it was sent and
     * its validity has not run out. Must run inside a transaction, so a double click or a second
     * request that decides the same quote finds it already decided.
     *
     * @throws InvalidQuote
     */
    public function handle(Quote $quote): Quote
    {
        $locked = Quote::query()->lockForUpdate()->findOrFail($quote->getKey());

        if ($locked->status !== QuoteStatus::Sent) {
            throw InvalidQuote::notSent();
        }

        if ($locked->isExpired()) {
            throw InvalidQuote::expired();
        }

        return $locked;
    }
}
