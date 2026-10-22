<?php

use App\Enums\Currency;
use App\Enums\QuoteDisplayStatus;
use App\Enums\QuoteStatus;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

it('casts status, dates and defaults to a draft', function () {
    $quote = Quote::factory()->create(['valid_until' => '2026-03-15'])->fresh();

    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->valid_until->format('d.m.Y'))->toBe('15.03.2026')
        ->and($quote->sent_at)->toBeNull();

    $sent = Quote::factory()->sent()->create()->fresh();

    expect($sent->status)->toBe(QuoteStatus::Sent)
        ->and($sent->sent_at)->toBeInstanceOf(Carbon::class);
});

it('has a unique number', function () {
    Quote::factory()->create(['number' => 'TKL-2026-0001']);

    expect(fn () => Quote::factory()->create(['number' => 'TKL-2026-0001']))
        ->toThrow(QueryException::class);
});

it('requires a customer or a recipient name and e-mail', function () {
    expect(fn () => Quote::factory()->create(['customer_id' => null]))->toThrow(QueryException::class)
        ->and(fn () => Quote::factory()->create(['customer_id' => null, 'recipient_name' => 'Ali Veli']))->toThrow(QueryException::class)
        ->and(fn () => Quote::factory()->create(['customer_id' => null, 'recipient_email' => 'ali@example.com']))->toThrow(QueryException::class);

    $manual = Quote::factory()->manualRecipient()->create();

    expect($manual->customer_id)->toBeNull()
        ->and($manual->recipient_name)->not->toBeNull()
        ->and($manual->recipient_email)->not->toBeNull();
});

it('belongs to a customer that has quotes', function () {
    $customer = Customer::factory()->create();
    $quote = Quote::factory()->for($customer)->create();

    expect($quote->customer->is($customer))->toBeTrue()
        ->and($customer->quotes->modelKeys())->toBe([$quote->id])
        ->and($customer->isInUse())->toBeTrue();
});

it('lists its items in sort order and deletes them with the quote', function () {
    $quote = Quote::factory()->create();
    $second = QuoteItem::factory()->for($quote)->create(['sort_order' => 2]);
    $first = QuoteItem::factory()->for($quote)->create(['sort_order' => 1]);

    expect($quote->items->modelKeys())->toBe([$first->id, $second->id]);

    $quote->delete();

    expect(QuoteItem::query()->count())->toBe(0);
});

describe('expiry', function () {
    it('is valid through the whole valid_until day and expires the day after', function () {
        $this->travelTo(Carbon::parse('2026-03-01 12:00', 'Europe/Istanbul'));
        $quote = Quote::factory()->sent()->create(['valid_until' => '2026-03-15']);

        $this->travelTo(Carbon::parse('2026-03-15 23:59', 'Europe/Istanbul'));
        expect($quote->isExpired())->toBeFalse()
            ->and($quote->displayStatus())->toBe(QuoteDisplayStatus::Sent);

        $this->travelTo(Carbon::parse('2026-03-16 00:01', 'Europe/Istanbul'));
        expect($quote->isExpired())->toBeTrue()
            ->and($quote->displayStatus())->toBe(QuoteDisplayStatus::Expired)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('only expires a sent quote', function (string $state, QuoteDisplayStatus $expected) {
        $quote = Quote::factory()->{$state}()->create(['valid_until' => now()->subMonth()->toDateString()]);

        expect($quote->isExpired())->toBe($expected === QuoteDisplayStatus::Expired)
            ->and($quote->displayStatus())->toBe($expected);
    })->with([
        'sent' => ['sent', QuoteDisplayStatus::Expired],
        'accepted' => ['accepted', QuoteDisplayStatus::Accepted],
        'rejected' => ['rejected', QuoteDisplayStatus::Rejected],
    ]);

    it('never expires a draft', function () {
        $quote = Quote::factory()->create(['valid_until' => now()->subMonth()->toDateString()]);

        expect($quote->isExpired())->toBeFalse()
            ->and($quote->displayStatus())->toBe(QuoteDisplayStatus::Draft);
    });

    it('has an expired factory state', function () {
        expect(Quote::factory()->expired()->create()->displayStatus())->toBe(QuoteDisplayStatus::Expired);
    });
});

describe('totals', function () {
    it('totals per currency without mixing them', function () {
        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create(['price' => 400000, 'currency' => Currency::Try]);
        QuoteItem::factory()->for($quote)->create(['price' => 150000, 'currency' => Currency::Try]);
        QuoteItem::factory()->for($quote)->create(['price' => 35000, 'currency' => Currency::Usd]);

        $totals = $quote->totals();

        expect(array_keys($totals))->toBe(['TRY', 'USD'])
            ->and($totals['TRY']->amount)->toBe(550000)
            ->and($totals['USD']->amount)->toBe(35000);
    });

    it('leaves free items out of the totals', function () {
        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->create(['price' => 400000]);
        QuoteItem::factory()->for($quote)->free()->create();

        expect($quote->totals()['TRY']->amount)->toBe(400000);
    });

    it('has no totals without paid items', function () {
        $quote = Quote::factory()->create();
        QuoteItem::factory()->for($quote)->free()->create();

        expect($quote->totals())->toBe([]);
    });
});

it('does not touch the quote when the catalog price changes later', function () {
    $catalogService = CatalogService::factory()->create(['price' => 400000]);
    $item = QuoteItem::factory()->create(['catalog_service_id' => $catalogService->id]);

    $catalogService->update(['price' => 500000]);

    expect($item->fresh()->price)->toBe(400000);
});

it('makes a catalog service or package on a quote in use', function () {
    $catalogService = CatalogService::factory()->create();
    $package = Package::factory()->create();

    expect($catalogService->isInUse())->toBeFalse()
        ->and($package->isInUse())->toBeFalse();

    QuoteItem::factory()->create(['catalog_service_id' => $catalogService->id]);
    QuoteItem::factory()->forPackage()->create(['package_id' => $package->id]);

    expect($catalogService->isInUse())->toBeTrue()
        ->and($package->isInUse())->toBeTrue();
});
