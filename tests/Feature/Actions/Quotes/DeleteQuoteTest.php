<?php

use App\Actions\Quotes\DeleteQuote;
use App\Exceptions\InvalidQuote;
use App\Models\Quote;
use App\Models\QuoteItem;

it('deletes a draft quote with its items', function () {
    $quote = Quote::factory()->create();
    QuoteItem::factory()->count(2)->for($quote)->create();

    app(DeleteQuote::class)->handle($quote);

    expect(Quote::count())->toBe(0)
        ->and(QuoteItem::count())->toBe(0);
});

it('keeps a quote that is not a draft', function (string $state) {
    $quote = Quote::factory()->{$state}()->create();
    QuoteItem::factory()->for($quote)->create();

    expect(fn () => app(DeleteQuote::class)->handle($quote))
        ->toThrow(InvalidQuote::class, __('quotes.not_draft'));

    expect(Quote::count())->toBe(1)
        ->and(QuoteItem::count())->toBe(1);
})->with(['sent', 'expired', 'accepted', 'rejected']);

it('does nothing for a quote that is already gone', function () {
    $quote = Quote::factory()->create();
    Quote::query()->whereKey($quote->id)->delete();

    app(DeleteQuote::class)->handle($quote);

    expect(Quote::count())->toBe(0);
});
