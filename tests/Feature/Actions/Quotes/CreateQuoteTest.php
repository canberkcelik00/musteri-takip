<?php

use App\Actions\Quotes\CreateQuote;
use App\Enums\Currency;
use App\Enums\QuoteErrorReason;
use App\Enums\QuoteStatus;
use App\Enums\SettingKey;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Quote;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('creates a draft quote for a customer with the catalog service as its first item', function () {
    $this->travelTo(Carbon::parse('2026-11-02 10:00', 'Europe/Istanbul'));
    $customer = Customer::factory()->create();
    $catalogService = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000, 'currency' => Currency::Usd]);

    $quote = app(CreateQuote::class)->handle($catalogService, $customer, ['notes' => 'Not']);

    expect($quote->number)->toBe('TKL-2026-0001')
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->customer_id)->toBe($customer->id)
        ->and($quote->recipient_name)->toBeNull()
        ->and($quote->valid_until->toDateString())->toBe('2026-11-17')
        ->and($quote->notes)->toBe('Not')
        ->and($quote->items)->toHaveCount(1)
        ->and($quote->items[0]->name)->toBe('Linux Hosting 1000 MB')
        ->and($quote->items[0]->price)->toBe(150000)
        ->and($quote->items[0]->currency)->toBe(Currency::Usd)
        ->and($quote->items[0]->catalog_service_id)->toBe($catalogService->id)
        ->and($quote->items[0]->package_id)->toBeNull()
        ->and($quote->items[0]->sort_order)->toBe(0);
});

it('quotes a package', function () {
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

    $quote = app(CreateQuote::class)->handle($package, Customer::factory()->create());

    expect($quote->items[0]->package_id)->toBe($package->id)
        ->and($quote->items[0]->catalog_service_id)->toBeNull()
        ->and($quote->items[0]->name)->toBe('Kurumsal Paket');
});

it('takes the validity from the setting', function () {
    $this->travelTo(Carbon::parse('2026-11-02 10:00', 'Europe/Istanbul'));
    app(Settings::class)->set(SettingKey::QuoteValidityDays, '30');

    $quote = app(CreateQuote::class)->handle(CatalogService::factory()->create(), Customer::factory()->create());

    expect($quote->valid_until->toDateString())->toBe('2026-12-02');
});

it('uses the given price, currency and free flag instead of the catalog values', function () {
    $catalogService = CatalogService::factory()->create(['price' => 150000, 'currency' => Currency::Try]);

    $quote = app(CreateQuote::class)->handle($catalogService, Customer::factory()->create(), [
        'price' => 120000,
        'currency' => Currency::Eur,
    ]);
    $free = app(CreateQuote::class)->handle($catalogService, Customer::factory()->create(), ['price' => 0, 'is_free' => true]);

    expect($quote->items[0]->price)->toBe(120000)
        ->and($quote->items[0]->currency)->toBe(Currency::Eur)
        ->and($free->items[0]->is_free)->toBeTrue()
        ->and($free->items[0]->price)->toBe(0);
});

it('rejects a zero or negative price unless the item is free', function (int $price) {
    expect(fn () => app(CreateQuote::class)->handle(CatalogService::factory()->create(), Customer::factory()->create(), ['price' => $price]))
        ->toThrow(InvalidQuote::class, __('quotes.price_not_positive'));

    expect(Quote::count())->toBe(0);
})->with([0, -100]);

it('creates a quote for a hand-written recipient', function () {
    $quote = app(CreateQuote::class)->handle(CatalogService::factory()->create(), null, [
        'recipient_name' => ' Ali Veli ',
        'recipient_company' => 'Veli Ltd.',
        'recipient_phone' => '0532 111 22 33',
        'recipient_email' => 'ali@example.com',
    ]);

    expect($quote->customer_id)->toBeNull()
        ->and($quote->recipient_name)->toBe('Ali Veli')
        ->and($quote->recipient_company)->toBe('Veli Ltd.')
        ->and($quote->recipient_phone)->toBe('0532 111 22 33')
        ->and($quote->recipient_email)->toBe('ali@example.com');
});

it('requires the name and e-mail of a hand-written recipient', function (array $recipient, QuoteErrorReason $reason) {
    $thrown = null;

    try {
        app(CreateQuote::class)->handle(CatalogService::factory()->create(), null, $recipient);
    } catch (InvalidQuote $exception) {
        $thrown = $exception;
    }

    expect($thrown?->reason)->toBe($reason)
        ->and(Quote::count())->toBe(0);
})->with([
    'nothing' => [[], QuoteErrorReason::RecipientRequired],
    'no e-mail' => [['recipient_name' => 'Ali Veli'], QuoteErrorReason::RecipientRequired],
    'no name' => [['recipient_email' => 'ali@example.com'], QuoteErrorReason::RecipientRequired],
    'blank name' => [['recipient_name' => '  ', 'recipient_email' => 'ali@example.com'], QuoteErrorReason::RecipientRequired],
    'invalid e-mail' => [['recipient_name' => 'Ali Veli', 'recipient_email' => 'ali'], QuoteErrorReason::RecipientEmailInvalid],
]);

it('ignores a hand-written recipient when a customer is given', function () {
    $quote = app(CreateQuote::class)->handle(CatalogService::factory()->create(), Customer::factory()->create(), [
        'recipient_name' => 'Ali Veli',
        'recipient_email' => 'ali@example.com',
    ]);

    expect($quote->recipient_name)->toBeNull()
        ->and($quote->recipient_email)->toBeNull();
});

it('does not quote an archived customer', function () {
    $customer = Customer::factory()->archived()->create();

    expect(fn () => app(CreateQuote::class)->handle(CatalogService::factory()->create(), $customer))
        ->toThrow(InvalidQuote::class, __('quotes.customer_archived'));
});

describe('number', function () {
    it('counts up within the year', function () {
        $this->travelTo(Carbon::parse('2026-05-01', 'Europe/Istanbul'));
        $catalogService = CatalogService::factory()->create();
        $customer = Customer::factory()->create();

        $numbers = collect(range(1, 3))
            ->map(fn () => app(CreateQuote::class)->handle($catalogService, $customer)->number);

        expect($numbers->all())->toBe(['TKL-2026-0001', 'TKL-2026-0002', 'TKL-2026-0003']);
    });

    it('starts again from 0001 in the new year', function () {
        $catalogService = CatalogService::factory()->create();
        $customer = Customer::factory()->create();

        $this->travelTo(Carbon::parse('2026-12-31 12:00', 'Europe/Istanbul'));
        app(CreateQuote::class)->handle($catalogService, $customer);
        app(CreateQuote::class)->handle($catalogService, $customer);

        $this->travelTo(Carbon::parse('2027-01-01 09:00', 'Europe/Istanbul'));
        $first = app(CreateQuote::class)->handle($catalogService, $customer);
        $second = app(CreateQuote::class)->handle($catalogService, $customer);

        expect($first->number)->toBe('TKL-2027-0001')
            ->and($second->number)->toBe('TKL-2027-0002');
    });

    it('goes on after the highest number even with gaps and beyond 9999', function () {
        $this->travelTo(Carbon::parse('2026-05-01', 'Europe/Istanbul'));
        Quote::factory()->create(['number' => 'TKL-2026-0007']);
        Quote::factory()->create(['number' => 'TKL-2026-9999']);

        $quote = app(CreateQuote::class)->handle(CatalogService::factory()->create(), Customer::factory()->create());
        $next = app(CreateQuote::class)->handle(CatalogService::factory()->create(), Customer::factory()->create());

        expect($quote->number)->toBe('TKL-2026-10000')
            ->and($next->number)->toBe('TKL-2026-10001');
    });

    it('retries when a concurrent creation took the number it computed', function () {
        $this->travelTo(Carbon::parse('2026-05-01', 'Europe/Istanbul'));
        $catalogService = CatalogService::factory()->create();
        $customer = Customer::factory()->create();
        $attempts = 0;

        // Another request inserts the same number first; the unique key makes this attempt fail.
        Quote::creating(function (Quote $quote) use (&$attempts, $customer): void {
            if (++$attempts === 1) {
                DB::table('quotes')->insert([
                    'number' => $quote->number,
                    'customer_id' => $customer->id,
                    'status' => 'draft',
                    'valid_until' => '2026-05-16',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $quote = app(CreateQuote::class)->handle($catalogService, $customer);

        expect($attempts)->toBe(2)
            ->and($quote->exists)->toBeTrue()
            ->and($quote->number)->toStartWith('TKL-2026-')
            ->and($quote->items)->toHaveCount(1)
            ->and(Quote::query()->where('number', $quote->number)->count())->toBe(1);
    });

    it('gives up after repeated number collisions', function () {
        $catalogService = CatalogService::factory()->create();
        $customer = Customer::factory()->create();

        Quote::creating(function (Quote $quote) use ($customer): void {
            DB::table('quotes')->insert([
                'number' => $quote->number,
                'customer_id' => $customer->id,
                'status' => 'draft',
                'valid_until' => now()->addDay()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        expect(fn () => app(CreateQuote::class)->handle($catalogService, $customer))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});

it('does not touch the quote when the catalog price changes later', function () {
    $catalogService = CatalogService::factory()->create(['price' => 400000]);
    $quote = app(CreateQuote::class)->handle($catalogService, Customer::factory()->create());

    $catalogService->update(['price' => 500000, 'name' => 'Yeni ad']);

    expect($quote->items()->first()->price)->toBe(400000)
        ->and($quote->items()->first()->name)->not->toBe('Yeni ad');
});
