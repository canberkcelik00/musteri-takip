<?php

namespace App\Actions\Quotes;

use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class DeleteQuote
{
    /**
     * Delete a draft quote with its items. A sent, accepted or rejected quote is kept.
     *
     * @throws InvalidQuote
     */
    public function handle(Quote $quote): void
    {
        DB::transaction(function () use ($quote): void {
            $locked = Quote::query()->lockForUpdate()->find($quote->getKey());

            if ($locked === null) {
                return;
            }

            if ($locked->status !== QuoteStatus::Draft) {
                throw InvalidQuote::notDraft();
            }

            $locked->delete();
        });
    }
}
