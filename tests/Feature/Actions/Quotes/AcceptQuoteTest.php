<?php

use App\Actions\Payments\RecordPayment;
use App\Actions\Quotes\AcceptQuote;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\QuoteErrorReason;
use App\Enums\QuoteStatus;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\QuoteItem;

beforeEach(function () {
    $this->travelTo('2027-03-01 10:00:00');
});

/**
 * A sent quote valid until 2027-03-15 for the given customer (a new one by default).
 *
 * @param  array<string, mixed>  $attributes
 */
function sentQuote(?Customer $customer = null, array $attributes = []): Quote
{
    return Quote::factory()->sent()->for($customer ?? Customer::factory()->create())->create([
        'valid_until' => '2027-03-15',
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function manualSentQuote(array $attributes = []): Quote
{
    return Quote::factory()->sent()->manualRecipient()->create([
        'valid_until' => '2027-03-15',
        'recipient_name' => 'Ali Veli',
        'recipient_company' => 'Veli Ltd.',
        'recipient_phone' => '0111 111 11 11',
        'recipient_email' => 'ali@example.com',
        ...$attributes,
    ]);
}

describe('existing customer', function () {
    it('creates a customer service from a catalog service item with the quoted values and the first charge', function () {
        $catalogService = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000]);
        $quote = sentQuote();
        $item = QuoteItem::factory()->for($quote)->create([
            'catalog_service_id' => $catalogService->id,
            'name' => 'Linux Hosting 1000 MB',
            'price' => 120000,
            'currency' => Currency::Usd,
        ]);
        $catalogService->update(['name' => 'Katalogda yeni ad', 'price' => 999900]);

        $accepted = app(AcceptQuote::class)->handle(actor(), $quote);

        $service = CustomerService::query()->sole();

        expect($accepted->status)->toBe(QuoteStatus::Accepted)
            ->and($accepted->accepted_at?->toDateTimeString())->toBe('2027-03-01 10:00:00')
            ->and($accepted->customer_id)->toBe($quote->customer_id)
            ->and($service->customer_id)->toBe($quote->customer_id)
            ->and($service->quote_item_id)->toBe($item->id)
            ->and($service->catalog_service_id)->toBe($catalogService->id)
            ->and($service->name)->toBe('Linux Hosting 1000 MB')
            ->and($service->price)->toBe(120000)
            ->and($service->currency)->toBe(Currency::Usd)
            ->and($service->is_free)->toBeFalse()
            ->and($service->start_date->toDateString())->toBe('2027-03-01')
            ->and($service->end_date->toDateString())->toBe('2028-03-01');

        $charge = Charge::query()->sole();

        expect($charge->customer_service_id)->toBe($service->id)
            ->and($charge->amount)->toBe(120000)
            ->and($charge->currency)->toBe(Currency::Usd)
            ->and($charge->due_date->toDateString())->toBe('2027-03-01')
            ->and($charge->status)->toBe(ChargeStatus::Open);
    });

    it('assigns a quoted package with its items', function () {
        $package = Package::factory()->withCatalogServices(3)->create(['name' => 'Kurumsal Paket', 'price' => 400000]);
        $quote = sentQuote();
        QuoteItem::factory()->forPackage()->for($quote)->create(['package_id' => $package->id, 'name' => 'Kurumsal Paket', 'price' => 400000]);

        app(AcceptQuote::class)->handle(actor(), $quote);

        $service = CustomerService::query()->sole();

        expect($service->package_id)->toBe($package->id)
            ->and($service->catalog_service_id)->toBeNull()
            ->and($service->items)->toHaveCount(3)
            ->and(Charge::query()->sole()->amount)->toBe(400000);
    });

    it('creates one service per item, all with the same start date', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create(['name' => 'Hosting', 'price' => 150000]);
        QuoteItem::factory()->for($quote)->create(['name' => 'Alan adı', 'price' => 30000, 'sort_order' => 1]);
        QuoteItem::factory()->forPackage()->for($quote)->create(['name' => 'Bakım paketi', 'price' => 90000, 'sort_order' => 2]);

        app(AcceptQuote::class)->handle(actor(), $quote, '2027-03-10');

        $services = CustomerService::query()->orderBy('id')->get();

        expect($services->pluck('name')->all())->toBe(['Hosting', 'Alan adı', 'Bakım paketi'])
            ->and($services->pluck('start_date')->map->toDateString()->unique()->all())->toBe(['2027-03-10'])
            ->and($services->pluck('quote_item_id')->unique())->toHaveCount(3)
            ->and(Charge::query()->count())->toBe(3);
    });

    it('marks a free item free and creates no charge for it', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create(['name' => 'Hosting', 'price' => 150000]);
        QuoteItem::factory()->for($quote)->free()->create(['name' => 'Bakım (ilk yıl)', 'sort_order' => 1]);

        app(AcceptQuote::class)->handle(actor(), $quote);

        $free = CustomerService::query()->where('name', 'Bakım (ilk yıl)')->sole();

        expect($free->is_free)->toBeTrue()
            ->and(Charge::query()->count())->toBe(1)
            ->and(Charge::query()->sole()->customer_service_id)->not->toBe($free->id);
    });

    it('uses the given start date', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create();

        app(AcceptQuote::class)->handle(actor(), $quote, '2027-04-01');

        $service = CustomerService::query()->sole();

        expect($service->start_date->toDateString())->toBe('2027-04-01')
            ->and($service->end_date->toDateString())->toBe('2028-04-01')
            ->and(Charge::query()->sole()->due_date->toDateString())->toBe('2027-04-01');
    });

    it('applies the customer credit balance to the first charge', function () {
        $customer = Customer::factory()->create();
        app(RecordPayment::class)->handle(actor(), $customer, [
            'amount' => 400000,
            'currency' => Currency::Try,
            'paid_on' => '2027-02-20',
            'payment_method_id' => PaymentMethod::query()->value('id'),
        ]);
        $quote = sentQuote($customer);
        QuoteItem::factory()->for($quote)->create(['price' => 300000, 'currency' => Currency::Try]);

        app(AcceptQuote::class)->handle(actor(), $quote);

        expect(Charge::query()->sole()->status)->toBe(ChargeStatus::Paid)
            ->and($customer->creditBalances()['TRY']->amount)->toBe(100000);
    });

    it('does not accept for an archived customer', function () {
        $quote = sentQuote(Customer::factory()->archived()->create());
        QuoteItem::factory()->for($quote)->create();

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))
            ->toThrow(InvalidQuote::class, __('quotes.customer_archived'));

        expect(CustomerService::count())->toBe(0)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });
});

describe('hand-written recipient', function () {
    it('creates the customer with the company as short name and the person as primary contact', function () {
        $quote = manualSentQuote();
        QuoteItem::factory()->for($quote)->create(['price' => 150000]);

        $accepted = app(AcceptQuote::class)->handle(actor(), $quote);

        $customer = Customer::query()->where('short_name', 'Veli Ltd.')->sole();
        $contact = $customer->primaryContact;

        expect($accepted->customer_id)->toBe($customer->id)
            ->and($accepted->status)->toBe(QuoteStatus::Accepted)
            ->and($customer->contacts)->toHaveCount(1)
            ->and($contact->name)->toBe('Ali Veli')
            ->and($contact->phone)->toBe('0111 111 11 11')
            ->and($contact->email)->toBe('ali@example.com')
            ->and($contact->is_primary)->toBeTrue()
            ->and(CustomerService::query()->sole()->customer_id)->toBe($customer->id)
            ->and(Charge::query()->sole()->customer_id)->toBe($customer->id);
    });

    it('uses the person name as short name when there is no company', function () {
        $quote = manualSentQuote(['recipient_company' => null]);
        QuoteItem::factory()->for($quote)->create();

        app(AcceptQuote::class)->handle(actor(), $quote);

        expect(Customer::query()->where('short_name', 'Ali Veli')->exists())->toBeTrue();
    });

    it('uses the given short name', function () {
        $quote = manualSentQuote();
        QuoteItem::factory()->for($quote)->create();

        app(AcceptQuote::class)->handle(actor(), $quote, null, '  Veli  ');

        expect(Customer::query()->where('short_name', 'Veli')->exists())->toBeTrue()
            ->and(Customer::query()->where('short_name', 'Veli Ltd.')->exists())->toBeFalse();
    });

    it('does not accept when the short name belongs to another customer and creates nothing', function () {
        Customer::factory()->create(['short_name' => 'Veli Ltd.']);
        $quote = manualSentQuote();
        QuoteItem::factory()->for($quote)->create();
        $thrown = null;

        try {
            app(AcceptQuote::class)->handle(actor(), $quote);
        } catch (InvalidQuote $exception) {
            $thrown = $exception;
        }

        expect($thrown?->reason)->toBe(QuoteErrorReason::ShortNameTaken)
            ->and(Customer::count())->toBe(1)
            ->and(CustomerService::count())->toBe(0)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent)
            ->and($quote->fresh()->customer_id)->toBeNull();

        app(AcceptQuote::class)->handle(actor(), $quote, null, 'Veli Ltd. (yeni)');

        expect(Customer::count())->toBe(2);
    });

    it('treats a short name that differs only in letter case as taken', function () {
        Customer::factory()->create(['short_name' => 'veli ltd.']);
        $quote = manualSentQuote();
        QuoteItem::factory()->for($quote)->create();

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))->toThrow(InvalidQuote::class, __('quotes.short_name_taken'));
    });

    it('ignores the given short name for an existing customer', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        $quote = sentQuote($customer);
        QuoteItem::factory()->for($quote)->create();

        app(AcceptQuote::class)->handle(actor(), $quote, null, 'Başka ad');

        expect(Customer::query()->where('short_name', 'Başka ad')->exists())->toBeFalse()
            ->and(CustomerService::query()->sole()->customer_id)->toBe($customer->id);
    });
});

describe('all or nothing', function () {
    it('creates nothing when a later item cannot be assigned', function () {
        $quote = manualSentQuote();
        QuoteItem::factory()->for($quote)->create(['price' => 150000]);
        QuoteItem::factory()->for($quote)->create(['price' => 0, 'is_free' => false, 'sort_order' => 1]);

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))->toThrow(InvalidCustomerService::class);

        expect(Customer::count())->toBe(0)
            ->and(Contact::count())->toBe(0)
            ->and(CustomerService::count())->toBe(0)
            ->and(Charge::count())->toBe(0)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent)
            ->and($quote->fresh()->accepted_at)->toBeNull();
    });

    it('refuses a start date whose period has already ended', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create();

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote, '2025-01-01'))
            ->toThrow(InvalidCustomerService::class, __('customer_services.period_already_ended'));

        expect(CustomerService::count())->toBe(0)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });
});

describe('who can accept', function () {
    it('cannot accept a quote that is not sent or is already decided', function (string $state) {
        $factory = $state === 'draft' ? Quote::factory() : Quote::factory()->{$state}();
        $quote = $factory->for(Customer::factory()->create())->create(['valid_until' => '2027-03-15']);
        QuoteItem::factory()->for($quote)->create();

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))->toThrow(InvalidQuote::class, __('quotes.not_sent'));

        expect(CustomerService::count())->toBe(0)
            ->and($quote->fresh()->status->value)->toBe($state === 'expired' ? 'sent' : $state);
    })->with(['draft', 'accepted', 'rejected']);

    it('cannot accept an expired quote', function () {
        $quote = sentQuote(attributes: ['valid_until' => '2027-02-28']);
        QuoteItem::factory()->for($quote)->create();

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))->toThrow(InvalidQuote::class, __('quotes.expired'));

        expect(CustomerService::count())->toBe(0)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('can accept on the last valid day but not the day after', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create();

        $this->travelTo('2027-03-15 23:30:00');
        expect(app(AcceptQuote::class)->handle(actor(), $quote)->status)->toBe(QuoteStatus::Accepted);

        $late = sentQuote();
        QuoteItem::factory()->for($late)->create();

        $this->travelTo('2027-03-16 00:30:00');
        expect(fn () => app(AcceptQuote::class)->handle(actor(), $late))->toThrow(InvalidQuote::class, __('quotes.expired'));
    });

    it('cannot accept the same quote twice', function () {
        $quote = sentQuote();
        QuoteItem::factory()->for($quote)->create();

        app(AcceptQuote::class)->handle(actor(), $quote);

        expect(fn () => app(AcceptQuote::class)->handle(actor(), $quote))->toThrow(InvalidQuote::class, __('quotes.not_sent'));

        expect(CustomerService::count())->toBe(1)
            ->and(Charge::count())->toBe(1);
    });
});
