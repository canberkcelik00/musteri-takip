<?php

use App\Enums\QuoteStatus;
use App\Filament\Resources\CatalogServices\Pages\ListCatalogServices;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\CatalogService;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Package;
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

/**
 * @param  array<string, mixed>  $attributes
 */
function listedQuote(string $state = 'draft', array $attributes = []): Quote
{
    $factory = $state === 'draft' ? Quote::factory() : Quote::factory()->{$state}();
    $customer = Customer::query()->firstWhere('short_name', 'Logi') ?? Customer::factory()->create(['short_name' => 'Logi']);
    $customer->contacts()->exists() || Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com']);

    $quote = $factory->for($customer)->create(['valid_until' => '2027-03-15', ...$attributes]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting', 'price' => 150000]);

    return $quote;
}

function quoteRowAction(string $name, Quote $quote): TestAction
{
    return TestAction::make($name)->table($quote);
}

describe('list', function () {
    it('lists the quotes newest first with number, recipient, total and state', function () {
        $old = listedQuote('draft', ['created_at' => '2027-02-01 09:00:00']);
        $new = listedQuote('sent', ['created_at' => '2027-02-20 09:00:00']);

        Livewire::test(ListQuotes::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)
            ->assertSee($new->number)
            ->assertSee('Logi')
            ->assertSee('1.500 TL')
            ->assertSee('Taslak')
            ->assertSee('Gönderildi');
    });

    it('shows the state it derives from the validity', function () {
        $expired = listedQuote('sent', ['valid_until' => '2027-02-28']);

        Livewire::test(ListQuotes::class)
            ->assertSee('Süresi doldu');

        expect($expired->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('says "Ücretsiz" as the total of a quote with only free items', function () {
        $quote = Quote::factory()->for(Customer::factory()->create())->create();
        QuoteItem::factory()->for($quote)->free()->create();

        Livewire::test(ListQuotes::class)->assertSee('Ücretsiz');
    });

    it('shows a hand-written recipient under the number', function () {
        $quote = Quote::factory()->manualRecipient()->create(['recipient_company' => 'Veli Ltd.', 'recipient_name' => 'Ali Veli']);

        Livewire::test(ListQuotes::class)->assertSee('Veli Ltd. · Ali Veli');
    });

    it('filters by the displayed state', function () {
        $draft = listedQuote('draft');
        $valid = listedQuote('sent');
        $expired = listedQuote('sent', ['valid_until' => '2027-02-28']);
        $accepted = listedQuote('accepted');

        Livewire::test(ListQuotes::class)
            ->filterTable('status', 'expired')
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$draft, $valid, $accepted])
            ->filterTable('status', 'sent')
            ->assertCanSeeTableRecords([$valid])
            ->assertCanNotSeeTableRecords([$expired, $draft, $accepted])
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$valid, $expired]);
    });

    it('searches the number, the customer and the written recipient', function () {
        $logi = listedQuote();
        $manual = Quote::factory()->manualRecipient()->create(['recipient_name' => 'Ali Veli', 'recipient_company' => 'Veli Ltd.']);

        Livewire::test(ListQuotes::class)
            ->searchTable($logi->number)
            ->assertCanSeeTableRecords([$logi])->assertCanNotSeeTableRecords([$manual])
            ->searchTable('Logi')
            ->assertCanSeeTableRecords([$logi])->assertCanNotSeeTableRecords([$manual])
            ->searchTable('Veli')
            ->assertCanSeeTableRecords([$manual])->assertCanNotSeeTableRecords([$logi]);
    });

    it('tells what to do when there are no quotes', function () {
        Livewire::test(ListQuotes::class)
            ->assertSee('Henüz teklif yok')
            ->assertActionExists(TestAction::make('createQuoteEmpty')->table());
    });

    it('is a menu item between Müşteriler and Tahsilat', function () {
        expect(QuoteResource::getNavigationLabel())->toBe('Teklifler');
    });
});

describe('row actions', function () {
    it('offers a draft "Düzenle" and "Gönder"', function () {
        $quote = listedQuote('draft');

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(quoteRowAction('editQuote', $quote))
            ->assertActionVisible(quoteRowAction('sendQuote', $quote))
            ->assertActionHidden(quoteRowAction('acceptQuote', $quote))
            ->assertActionHidden(quoteRowAction('rejectQuote', $quote));
    });

    it('offers a sent quote that is still valid "Kabul et" and "Reddet"', function () {
        $quote = listedQuote('sent');

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(quoteRowAction('acceptQuote', $quote))
            ->assertActionVisible(quoteRowAction('rejectQuote', $quote))
            ->assertActionHidden(quoteRowAction('editQuote', $quote))
            ->assertActionHidden(quoteRowAction('sendQuote', $quote));
    });

    it('offers an expired quote only "Tekrar gönder"', function () {
        $quote = listedQuote('sent', ['valid_until' => '2027-02-28']);

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(quoteRowAction('sendQuote', $quote))
            ->assertActionHidden(quoteRowAction('acceptQuote', $quote))
            ->assertActionHidden(quoteRowAction('rejectQuote', $quote))
            ->assertActionHidden(quoteRowAction('editQuote', $quote));
    });

    it('offers a decided quote no row button', function (string $state) {
        $quote = listedQuote($state);

        Livewire::test(ListQuotes::class)
            ->assertActionHidden(quoteRowAction('editQuote', $quote))
            ->assertActionHidden(quoteRowAction('sendQuote', $quote))
            ->assertActionHidden(quoteRowAction('acceptQuote', $quote))
            ->assertActionHidden(quoteRowAction('rejectQuote', $quote));
    })->with(['accepted', 'rejected']);
});

describe('create', function () {
    it('starts a draft from a catalog service row for a customer and opens the list', function () {
        $service = CatalogService::factory()->create(['name' => 'Linux Hosting 1000 MB', 'price' => 150000]);
        $customer = Customer::factory()->create();

        Livewire::test(ListCatalogServices::class)
            ->callAction(TestAction::make('createQuote')->table($service), ['customer_id' => $customer->id, 'notes' => 'Not'])
            ->assertHasNoFormErrors()
            ->assertNotified(__('quotes.created', ['number' => 'TKL-2027-0001']))
            ->assertRedirect(QuoteResource::getUrl('index'));

        $quote = Quote::query()->sole();

        expect($quote->customer_id)->toBe($customer->id)
            ->and($quote->status)->toBe(QuoteStatus::Draft)
            ->and($quote->notes)->toBe('Not')
            ->and($quote->items->sole()->catalog_service_id)->toBe($service->id)
            ->and($quote->items->sole()->price)->toBe(150000);
    });

    it('starts a draft from a package row for a hand-written recipient', function () {
        $package = Package::factory()->withCatalogServices(2)->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

        Livewire::test(ListPackages::class)
            ->callAction(TestAction::make('createQuote')->table($package), [
                'recipient_name' => 'Ali Veli',
                'recipient_company' => 'Veli Ltd.',
                'recipient_email' => 'ali@example.com',
            ])
            ->assertHasNoFormErrors();

        $quote = Quote::query()->sole();

        expect($quote->customer_id)->toBeNull()
            ->and($quote->recipient_name)->toBe('Ali Veli')
            ->and($quote->items->sole()->package_id)->toBe($package->id);
    });

    it('does not offer a quote for an archived catalog service or package', function () {
        $archived = CatalogService::factory()->archived()->create();
        $active = CatalogService::factory()->create();

        Livewire::test(ListCatalogServices::class)
            ->filterTable('archived', null)
            ->assertActionHidden(TestAction::make('createQuote')->table($archived))
            ->assertActionVisible(TestAction::make('createQuote')->table($active));
    });

    it('starts a draft from "Yeni teklif" with a picked catalog service', function () {
        $service = CatalogService::factory()->create(['price' => 90000]);

        Livewire::test(ListQuotes::class)
            ->callAction('createQuote', [
                'sold' => 'catalog_service:'.$service->id,
                'recipient_name' => 'Ali Veli',
                'recipient_email' => 'ali@example.com',
            ])
            ->assertHasNoFormErrors();

        expect(Quote::query()->sole()->items->sole()->catalog_service_id)->toBe($service->id);
    });

    it('asks for the name and e-mail of a hand-written recipient', function () {
        $service = CatalogService::factory()->create();

        Livewire::test(ListCatalogServices::class)
            ->callAction(TestAction::make('createQuote')->table($service), ['recipient_name' => null, 'recipient_email' => null])
            ->assertHasFormErrors(['recipient_name' => 'required', 'recipient_email' => 'required']);

        expect(Quote::count())->toBe(0);
    });

    it('does not take an archived customer', function () {
        $service = CatalogService::factory()->create();
        $archived = Customer::factory()->archived()->create();

        Livewire::test(ListCatalogServices::class)
            ->callAction(TestAction::make('createQuote')->table($service), ['customer_id' => $archived->id])
            ->assertHasFormErrors(['customer_id']);

        expect(Quote::count())->toBe(0);
    });
});
