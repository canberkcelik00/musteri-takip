<?php

use App\Enums\QuoteStatus;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\QuotesRelationManager;
use App\Filament\Support\RecordActionsSection;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2027-03-01 10:00:00');
});

function quotesCard(Customer $customer)
{
    return Livewire::test(QuotesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function customerQuote(Customer $customer, string $state = 'draft', array $attributes = []): Quote
{
    $factory = $state === 'draft' ? Quote::factory() : Quote::factory()->{$state}();
    $quote = $factory->for($customer)->create(['valid_until' => '2027-03-15', ...$attributes]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting', 'price' => 150000]);

    return $quote;
}

it('shows the Teklifler section on the card only for a customer that has quotes', function () {
    $without = Customer::factory()->create();
    $with = Customer::factory()->create();
    customerQuote($with);

    Livewire::test(ViewCustomer::class, ['record' => $without->getKey()])
        ->assertDontSeeLivewire(QuotesRelationManager::class);

    Livewire::test(ViewCustomer::class, ['record' => $with->getKey()])
        ->assertSeeLivewire(QuotesRelationManager::class);
});

it('lists the customer’s quotes newest first with number, total and state', function () {
    $customer = Customer::factory()->create();
    $old = customerQuote($customer, 'draft', ['created_at' => '2027-02-01 09:00:00']);
    $new = customerQuote($customer, 'accepted', ['created_at' => '2027-02-20 09:00:00']);
    $other = customerQuote(Customer::factory()->create());

    quotesCard($customer)
        ->assertSee('Teklifler')
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertCanNotSeeTableRecords([$other])
        ->assertSee($new->number)
        ->assertSee('1.500 TL')
        ->assertSee('Kabul edildi')
        ->assertSee('Taslak');
});

it('shows a sent quote past its validity as expired', function () {
    $customer = Customer::factory()->create();
    customerQuote($customer, 'sent', ['valid_until' => '2027-02-28']);

    quotesCard($customer)->assertSee('Süresi doldu');
});

it('has no button to add a quote', function () {
    $customer = Customer::factory()->create();
    customerQuote($customer);

    quotesCard($customer)
        ->assertActionDoesNotExist(TestAction::make('createQuote')->table())
        ->assertActionDoesNotExist(TestAction::make('create')->table());
});

it('opens the quote window from the number, with its actions', function () {
    $customer = Customer::factory()->create();
    $quote = customerQuote($customer, 'sent');

    quotesCard($customer)
        ->assertActionVisible([TestAction::make('openQuote')->table($quote), TestAction::make('downloadQuotePdf')->schemaComponent(RecordActionsSection::ACTIONS_KEY)])
        ->assertActionVisible([TestAction::make('openQuote')->table($quote), TestAction::make('acceptQuoteFromWindow')->schemaComponent(RecordActionsSection::ACTIONS_KEY)]);
});

it('accepts a quote from the card and creates the services and the first charge for the customer', function () {
    $customer = Customer::factory()->create();
    $quote = customerQuote($customer, 'sent');

    quotesCard($customer)
        ->callAction([TestAction::make('openQuote')->table($quote), TestAction::make('acceptQuoteFromWindow')->schemaComponent(RecordActionsSection::ACTIONS_KEY)])
        ->assertNotified('Teklif kabul edildi: 1 hizmet oluştu.');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
        ->and(CustomerService::query()->sole()->customer_id)->toBe($customer->id)
        ->and(Charge::query()->sole()->customer_id)->toBe($customer->id);
});
