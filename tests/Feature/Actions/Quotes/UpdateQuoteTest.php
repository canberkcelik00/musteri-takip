<?php

use App\Actions\Quotes\UpdateQuote;
use App\Enums\Currency;
use App\Enums\QuoteErrorReason;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;

/**
 * @return array{0: Quote, 1: QuoteItem}
 */
function draftQuoteWithItem(): array
{
    $quote = Quote::factory()->create(['notes' => 'Eski not']);
    $item = QuoteItem::factory()->for($quote)->create(['name' => 'Eski ad', 'price' => 100000]);

    return [$quote, $item];
}

it('changes the recipient and notes of a draft', function () {
    [$quote, $item] = draftQuoteWithItem();

    app(UpdateQuote::class)->handle($quote, null, [
        'recipient_name' => 'Ali Veli',
        'recipient_email' => 'ali@example.com',
        'notes' => 'Yeni not',
    ], [['id' => $item->id]]);

    $quote->refresh();

    expect($quote->customer_id)->toBeNull()
        ->and($quote->recipient_name)->toBe('Ali Veli')
        ->and($quote->notes)->toBe('Yeni not');

    $customer = Customer::factory()->create();
    app(UpdateQuote::class)->handle($quote, $customer, [], [['id' => $item->id]]);

    $quote->refresh();

    expect($quote->customer_id)->toBe($customer->id)
        ->and($quote->recipient_name)->toBeNull()
        ->and($quote->notes)->toBeNull();
});

it('keeps an existing item name while changing its price, currency and free flag', function () {
    [$quote, $item] = draftQuoteWithItem();
    $item->catalogService->update(['name' => 'Katalogda yeni ad']);

    app(UpdateQuote::class)->handle($quote, $quote->customer, [], [
        ['id' => $item->id, 'price' => 90000, 'currency' => Currency::Usd],
    ]);

    $item->refresh();

    expect($item->name)->toBe('Eski ad')
        ->and($item->price)->toBe(90000)
        ->and($item->currency)->toBe(Currency::Usd);

    app(UpdateQuote::class)->handle($quote, $quote->customer, [], [['id' => $item->id, 'price' => 0, 'is_free' => true]]);

    expect($item->fresh()->is_free)->toBeTrue();
});

it('adds items from the catalog, removes the ones left out and orders by position', function () {
    [$quote, $first] = draftQuoteWithItem();
    $removed = QuoteItem::factory()->for($quote)->create();
    $catalogService = CatalogService::factory()->create(['name' => 'Alan adı', 'price' => 30000]);
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

    $quote = app(UpdateQuote::class)->handle($quote, $quote->customer, [], [
        ['package_id' => $package->id],
        ['id' => $first->id],
        ['catalog_service_id' => $catalogService->id, 'price' => 25000],
    ]);

    expect($quote->items->pluck('name')->all())->toBe(['Kurumsal Paket', 'Eski ad', 'Alan adı'])
        ->and($quote->items->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($quote->items[2]->price)->toBe(25000)
        ->and(QuoteItem::query()->whereKey($removed->id)->exists())->toBeFalse();
});

it('needs at least one item', function () {
    [$quote] = draftQuoteWithItem();

    expect(fn () => app(UpdateQuote::class)->handle($quote, $quote->customer, [], []))
        ->toThrow(InvalidQuote::class, __('quotes.item_required'));
});

it('rejects items that are not valid and changes nothing', function (Closure $items, QuoteErrorReason $reason) {
    [$quote, $item] = draftQuoteWithItem();
    $other = QuoteItem::factory()->create();
    $thrown = null;

    try {
        app(UpdateQuote::class)->handle($quote, $quote->customer, ['notes' => 'Değişti'], $items($item, $other));
    } catch (InvalidQuote $exception) {
        $thrown = $exception;
    }

    expect($thrown?->reason)->toBe($reason)
        ->and($quote->fresh()->notes)->toBe('Eski not')
        ->and($quote->items()->pluck('id')->all())->toBe([$item->id])
        ->and($item->fresh()->price)->toBe(100000);
})->with([
    'item of another quote' => [fn (QuoteItem $item, QuoteItem $other) => [['id' => $other->id]], QuoteErrorReason::InvalidItem],
    'new item without a catalog entry' => [fn () => [['price' => 1000]], QuoteErrorReason::InvalidItem],
    'new item with both' => [fn () => [['catalog_service_id' => CatalogService::factory()->create()->id, 'package_id' => Package::factory()->create()->id]], QuoteErrorReason::InvalidItem],
    'zero price' => [fn (QuoteItem $item) => [['id' => $item->id, 'price' => 0]], QuoteErrorReason::PriceNotPositive],
]);

it('cannot change a quote that is not a draft', function (string $state) {
    $quote = Quote::factory()->{$state}()->create(['notes' => 'Eski not']);
    $item = QuoteItem::factory()->for($quote)->create();

    expect(fn () => app(UpdateQuote::class)->handle($quote, $quote->customer, ['notes' => 'Yeni'], [['id' => $item->id, 'price' => 1]]))
        ->toThrow(InvalidQuote::class, __('quotes.not_draft'));

    expect($quote->fresh()->notes)->toBe('Eski not')
        ->and($item->fresh()->price)->not->toBe(1);
})->with(['sent', 'expired', 'accepted', 'rejected']);

it('does not lock out a stale model of a quote that was sent meanwhile', function () {
    [$quote, $item] = draftQuoteWithItem();
    Quote::query()->whereKey($quote->id)->update(['status' => 'sent']);

    expect(fn () => app(UpdateQuote::class)->handle($quote, $quote->customer, [], [['id' => $item->id]]))
        ->toThrow(InvalidQuote::class, __('quotes.not_draft'));
});
