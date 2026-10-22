<?php

use App\Actions\Quotes\RejectQuote;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidQuote;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Quote;
use App\Models\QuoteItem;

beforeEach(function () {
    $this->travelTo('2027-03-01 10:00:00');
});

it('closes a sent quote as rejected and creates no record', function () {
    $quote = Quote::factory()->sent()->manualRecipient()->create(['valid_until' => '2027-03-15']);
    QuoteItem::factory()->for($quote)->create();

    $rejected = app(RejectQuote::class)->handle($quote);

    expect($rejected->status)->toBe(QuoteStatus::Rejected)
        ->and($rejected->rejected_at?->toDateTimeString())->toBe('2027-03-01 10:00:00')
        ->and($rejected->accepted_at)->toBeNull()
        ->and($rejected->customer_id)->toBeNull()
        ->and(Customer::count())->toBe(0)
        ->and(CustomerService::count())->toBe(0)
        ->and(Charge::count())->toBe(0)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Rejected);
});

it('can reject on the last valid day', function () {
    $quote = Quote::factory()->sent()->create(['valid_until' => '2027-03-01']);

    expect(app(RejectQuote::class)->handle($quote)->status)->toBe(QuoteStatus::Rejected);
});

it('cannot reject an expired quote', function () {
    $quote = Quote::factory()->sent()->create(['valid_until' => '2027-02-28']);

    expect(fn () => app(RejectQuote::class)->handle($quote))->toThrow(InvalidQuote::class, __('quotes.expired'));

    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('cannot reject a draft or a quote that is already decided', function (string $state) {
    $quote = ($state === 'draft' ? Quote::factory() : Quote::factory()->{$state}())->create(['valid_until' => '2027-03-15']);

    expect(fn () => app(RejectQuote::class)->handle($quote))->toThrow(InvalidQuote::class, __('quotes.not_sent'));

    expect($quote->fresh()->status->value)->toBe($state);
})->with(['draft', 'accepted', 'rejected']);

it('cannot reject twice', function () {
    $quote = Quote::factory()->sent()->create(['valid_until' => '2027-03-15']);

    app(RejectQuote::class)->handle($quote);

    expect(fn () => app(RejectQuote::class)->handle($quote))->toThrow(InvalidQuote::class, __('quotes.not_sent'));
});
