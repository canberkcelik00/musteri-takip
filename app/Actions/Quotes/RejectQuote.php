<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class RejectQuote
{
    public function __construct(private LockQuoteForDecision $lockQuote) {}

    /**
     * Close a sent, still valid quote as rejected. No customer, service or charge is created.
     *
     * @throws InvalidQuote
     */
    public function handle(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $locked = $this->lockQuote->handle($quote);

            $locked->update([
                'status' => QuoteStatus::Rejected,
                'rejected_at' => now(),
            ]);

            return $locked;
        });
    }
}
