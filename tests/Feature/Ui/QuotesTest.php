<?php

use App\Enums\QuoteStatus;
use App\Livewire\Pages\Quotes\Index;
use App\Livewire\Windows\AcceptQuote;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\CreateQuote;
use App\Livewire\Windows\OpenQuote;
use App\Livewire\Windows\SendQuote;
use App\Mail\QuoteMail;
use App\Models\CatalogService;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Support\Ui\QuotableItems;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo('2027-03-01 10:00:00');
});

/**
 * A quote for a customer whose primary contact has an e-mail, with one item of 1.500 TL.
 *
 * @param  array<string, mixed>  $attributes
 */
function uiQuote(string $state = 'draft', array $attributes = []): Quote
{
    $factory = $state === 'draft' ? Quote::factory() : Quote::factory()->{$state}();
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com']);

    $quote = $factory->for($customer)->create(['valid_until' => '2027-03-15', ...$attributes]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting', 'price' => 150000]);

    return $quote;
}

it('lists quotes newest first and filters by displayed status', function () {
    $draft = uiQuote('draft', ['number' => 'TKL-2027-0001']);
    $sent = uiQuote('sent', ['number' => 'TKL-2027-0002']);
    $expired = uiQuote('expired', ['number' => 'TKL-2027-0003', 'valid_until' => '2027-02-28']);

    Livewire::test(Index::class)
        ->assertSee('TKL-2027-0001')->assertSee('TKL-2027-0002')->assertSee('TKL-2027-0003')
        ->set('status', 'draft')->assertSee('TKL-2027-0001')->assertDontSee('TKL-2027-0002')
        ->set('status', 'expired')->assertSee('TKL-2027-0003')->assertDontSee('TKL-2027-0002')
        ->set('status', 'sent')->assertSee('TKL-2027-0002')->assertDontSee('TKL-2027-0003');

    expect([$draft->exists, $sent->exists, $expired->exists])->each->toBeTrue();
});

it('searches by number and recipient', function () {
    uiQuote('draft', ['number' => 'TKL-2027-0001']);
    uiQuote('draft', ['number' => 'TKL-2027-0002']);

    Livewire::test(Index::class)
        ->set('search', '0002')->assertSee('TKL-2027-0002')->assertDontSee('TKL-2027-0001');
});

it('shows the empty state without quotes', function () {
    Livewire::test(Index::class)->assertSee(__('quotes.empty_heading'));
});

it('offers the row buttons of each state', function () {
    uiQuote('draft', ['number' => 'TKL-2027-0001']);
    uiQuote('sent', ['number' => 'TKL-2027-0002']);
    uiQuote('expired', ['number' => 'TKL-2027-0003', 'valid_until' => '2027-02-28']);
    uiQuote('accepted', ['number' => 'TKL-2027-0004']);

    $html = Livewire::test(Index::class)->html();

    expect($html)->toContain(__('quotes.send'))->toContain(__('quotes.accept'))->toContain(__('quotes.reject'))->toContain(__('quotes.resend'));
});

it('opens a draft in an editable window and saves it', function () {
    $quote = uiQuote('draft');
    $item = $quote->items()->first();

    Livewire::test(OpenQuote::class, ['quote' => $quote->id])
        ->assertSet('items.0.name', 'Linux Hosting')
        ->assertSet('items.0.price', '1.500')
        ->set('notes', 'Yıllık')
        ->set('items.0.price', '1.800')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('quotes-changed')
        ->assertDispatched('close-window');

    expect($quote->fresh()->notes)->toBe('Yıllık')
        ->and($item->fresh()->price)->toBe(180000);
});

it('does not save a quote that was already sent', function () {
    $quote = uiQuote('sent');

    Livewire::test(OpenQuote::class, ['quote' => $quote->id])
        ->set('notes', 'Değişti')
        ->call('addItem')
        ->call('save')
        ->assertNotDispatched('quotes-changed')
        ->assertCount('items', 1);

    expect($quote->fresh()->notes)->toBeNull();
});

it('adds a catalog line with its price, removes and reorders lines', function () {
    $service = CatalogService::factory()->create(['name' => 'Alan adı', 'price' => 25000]);
    $quote = uiQuote('draft');

    Livewire::test(OpenQuote::class, ['quote' => $quote->id])
        ->call('addItem')
        ->set('items.1.item', QuotableItems::keyOf($service))
        ->assertSet('items.1.price', '250')
        ->call('moveItem', 1, -1)
        ->assertSet('items.0.item', QuotableItems::keyOf($service))
        ->call('removeItem', 0)
        ->assertCount('items', 1)
        ->assertSet('items.0.name', 'Linux Hosting');
});

it('requires a recipient when the quote has no customer', function () {
    $quote = Quote::factory()->manualRecipient()->create();

    Livewire::test(OpenQuote::class, ['quote' => $quote->id])
        ->set('recipient_name', '')
        ->set('recipient_email', '')
        ->call('save')
        ->assertHasErrors(['recipient_name', 'recipient_email']);
});

it('creates a draft for a catalog item and a customer', function () {
    $service = CatalogService::factory()->create(['name' => 'Linux Hosting']);
    $customer = Customer::factory()->create();

    Livewire::test(CreateQuote::class, ['sold' => QuotableItems::keyOf($service)])
        ->set('customer_id', (string) $customer->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $quote = Quote::query()->firstOrFail();
    expect($quote->status)->toBe(QuoteStatus::Draft)->and($quote->customer_id)->toBe($customer->id)->and($quote->items)->toHaveCount(1);
});

it('asks the item from the list when no item is given', function () {
    Livewire::test(CreateQuote::class)
        ->set('recipient_name', 'Ali')->set('recipient_email', 'ali@example.com')
        ->call('save')
        ->assertHasErrors(['sold']);

    expect(Quote::query()->count())->toBe(0);
});

it('creates a draft for a hand-written recipient', function () {
    $service = CatalogService::factory()->create();

    Livewire::test(CreateQuote::class)
        ->set('sold', QuotableItems::keyOf($service))
        ->set('recipient_name', 'Ali Veli')
        ->set('recipient_email', 'ali@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Quote::query()->firstOrFail()->recipient_name)->toBe('Ali Veli');
});

it('sends a draft by e-mail', function () {
    Mail::fake();
    $quote = uiQuote('draft');

    Livewire::test(SendQuote::class, ['quote' => $quote->id])
        ->assertSet('recipient_email', 'ayse@example.com')
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('quotes-changed');

    Mail::assertSent(QuoteMail::class, 1);
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
});

it('shows a plain message when the e-mail cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new TransportException('smtp secret'));
    $quote = uiQuote('draft');

    Livewire::test(SendQuote::class, ['quote' => $quote->id])
        ->call('send')
        ->assertHasErrors(['form'])
        ->assertDontSee('smtp secret');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Draft);
});

it('accepts a sent quote for an existing customer', function () {
    $quote = uiQuote('sent');

    Livewire::test(AcceptQuote::class, ['quote' => $quote->id])
        ->assertSet('start_date', '2027-03-01')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('quotes-changed')
        ->assertDispatched('close-window');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
});

it('rejects a sent quote and deletes a draft through the confirm window', function () {
    $sent = uiQuote('sent');
    $draft = uiQuote('draft');

    Livewire::test(Confirm::class, ['action' => 'reject-quote', 'id' => $sent->id])->call('confirm');
    Livewire::test(Confirm::class, ['action' => 'delete-quote', 'id' => $draft->id])->call('confirm');

    expect($sent->fresh()->status)->toBe(QuoteStatus::Rejected)->and(Quote::query()->find($draft->id))->toBeNull();
});

it('downloads the quote as a PDF and needs a signed-in user', function () {
    $quote = uiQuote('draft');

    $this->get(route('app.quotes.pdf', $quote))->assertOk()->assertHeader('content-type', 'application/pdf');

    auth()->logout();

    $this->get(route('app.quotes.pdf', $quote))->assertRedirect(route('app.login'));
});

it('is reachable at /app/quotes', function () {
    $this->get(route('app.quotes.index'))->assertOk()->assertSee(__('quotes.plural'));
});
