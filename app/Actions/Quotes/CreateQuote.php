<?php

namespace App\Actions\Quotes;

use App\Enums\Currency;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateQuote
{
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(
        private NormalizeQuoteRecipient $normalizeRecipient,
        private BuildQuoteItem $buildItem,
        private Settings $settings,
    ) {}

    /**
     * Create a draft quote starting with the clicked catalog service or package. The recipient is
     * an existing customer or a hand-written person; the item's price and currency are the
     * catalog's unless given. The quote is valid from today for the configured number of days and
     * gets the next `TKL-YYYY-NNNN` number of the year; a number taken by a concurrent creation
     * is retried with the next one.
     *
     * @param  array{recipient_name?: ?string, recipient_company?: ?string, recipient_phone?: ?string, recipient_email?: ?string, notes?: ?string, price?: int, currency?: Currency, is_free?: bool}  $attributes
     *
     * @throws InvalidQuote
     */
    public function handle(CatalogService|Package $quoted, ?Customer $customer, array $attributes = []): Quote
    {
        $recipient = $this->normalizeRecipient->handle($customer, $attributes);
        $item = $this->buildItem->handle($quoted, $attributes);
        $today = Carbon::today(config('app.timezone'));

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($recipient, $item, $attributes, $today): Quote {
                    $quote = Quote::create([
                        ...$recipient,
                        'number' => $this->nextNumber($today),
                        'valid_until' => $today->copy()->addDays($this->settings->quoteValidityDays())->toDateString(),
                        'notes' => $attributes['notes'] ?? null,
                    ]);

                    $quote->items()->create([...$item, 'sort_order' => 0]);

                    return $quote;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * The year's highest sequence plus one, as `TKL-YYYY-NNNN`.
     */
    private function nextNumber(Carbon $today): string
    {
        $prefix = 'TKL-'.$today->year.'-';

        $last = (int) Quote::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, ?) AS UNSIGNED)) as last', [strlen($prefix) + 1])
            ->value('last');

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
