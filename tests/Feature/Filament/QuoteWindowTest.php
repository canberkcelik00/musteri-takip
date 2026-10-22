<?php

use App\Enums\Currency;
use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Support\RecordActionsSection;
use App\Mail\QuoteMail;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2027-03-01 10:00:00');
});

/**
 * A quote for a customer whose primary contact has an e-mail, with one item of 1.500 TL.
 *
 * @param  array<string, mixed>  $attributes
 */
function windowQuote(string $state = 'draft', array $attributes = []): Quote
{
    $factory = $state === 'draft' ? Quote::factory() : Quote::factory()->{$state}();
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.com']);

    $quote = $factory->for($customer)->create(['valid_until' => '2027-03-15', ...$attributes]);
    QuoteItem::factory()->for($quote)->create(['name' => 'Linux Hosting', 'price' => 150000]);

    return $quote;
}

function windowOf(Quote $quote): TestAction
{
    return TestAction::make('openQuote')->table($quote);
}

function windowAction(string $name, Quote $quote, string $section = RecordActionsSection::ACTIONS_KEY): array
{
    return [windowOf($quote), TestAction::make($name)->schemaComponent($section)];
}

describe('draft window', function () {
    it('opens with the quote filled in', function () {
        $quote = windowQuote('draft', ['notes' => 'Not']);

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->assertSchemaStateSet([
                'customer_id' => $quote->customer_id,
                'notes' => 'Not',
            ])
            ->assertSchemaStateSet(function (array $state) use ($quote): array {
                // The repeater keys its rows by a generated id; only their content matters.
                expect(array_values($state['items']))->toEqual([
                    ['id' => $quote->items->sole()->id, 'name' => 'Linux Hosting', 'price' => '1.500', 'currency' => 'TRY', 'is_free' => false, 'item' => null],
                ]);

                return [];
            });
    });

    it('is also opened by the row button "Düzenle"', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('editQuote')->table($quote))
            ->assertSchemaStateSet(['customer_id' => $quote->customer_id]);
    });

    it('saves a new price, an added catalog service and the notes', function () {
        $quote = windowQuote();
        $item = $quote->items->sole();
        $domain = CatalogService::factory()->create(['name' => 'Alan adı', 'price' => 30000, 'currency' => Currency::Usd]);

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm([
                'notes' => 'Yeni not',
                'items' => [
                    ['id' => $item->id, 'name' => 'Linux Hosting', 'price' => '1.200', 'currency' => 'TRY', 'is_free' => false],
                    ['id' => null, 'item' => 'catalog_service:'.$domain->id, 'price' => '300', 'currency' => 'USD', 'is_free' => false],
                ],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Teklif kaydedildi.');

        $quote->refresh();

        expect($quote->notes)->toBe('Yeni not')
            ->and($quote->items->pluck('name')->all())->toBe(['Linux Hosting', 'Alan adı'])
            ->and($quote->items[0]->price)->toBe(120000)
            ->and($quote->items[1]->price)->toBe(30000)
            ->and($quote->items[1]->currency)->toBe(Currency::Usd)
            ->and($quote->items[1]->catalog_service_id)->toBe($domain->id);
    });

    it('adds a package line', function () {
        $quote = windowQuote();
        $item = $quote->items->sole();
        $package = Package::factory()->withCatalogServices(2)->create(['name' => 'Kurumsal Paket', 'price' => 400000]);

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm([
                'items' => [
                    ['id' => $item->id, 'price' => '1.500', 'currency' => 'TRY'],
                    ['id' => null, 'item' => 'package:'.$package->id, 'price' => '4.000', 'currency' => 'TRY'],
                ],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect($quote->fresh()->items->last()->package_id)->toBe($package->id);
    });

    it('removes a line and marks another free', function () {
        $quote = windowQuote();
        $first = $quote->items->sole();
        $second = QuoteItem::factory()->for($quote)->create(['sort_order' => 1]);

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm(['items' => [['id' => $second->id, 'price' => null, 'currency' => 'TRY', 'is_free' => true]]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $quote->refresh();

        expect($quote->items->pluck('id')->all())->toBe([$second->id])
            ->and($quote->items->sole()->is_free)->toBeTrue()
            ->and(QuoteItem::query()->whereKey($first->id)->exists())->toBeFalse();
    });

    it('changes the recipient to a hand-written one', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm([
                'customer_id' => null,
                'recipient_name' => 'Ali Veli',
                'recipient_email' => 'ali@example.com',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $quote->refresh();

        expect($quote->customer_id)->toBeNull()
            ->and($quote->recipient_name)->toBe('Ali Veli');
    });

    it('asks for a recipient and for at least one line', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm(['customer_id' => null, 'recipient_name' => null, 'recipient_email' => null, 'items' => []])
            ->callMountedAction()
            ->assertHasFormErrors(['recipient_name' => 'required', 'recipient_email' => 'required', 'items']);
    });

    it('asks for a price unless the line is free', function () {
        $quote = windowQuote();
        $item = $quote->items->sole();

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->fillForm(['items' => [['id' => $item->id, 'price' => null, 'currency' => 'TRY', 'is_free' => false]]])
            ->callMountedAction()
            ->assertHasFormErrors();

        expect($item->fresh()->price)->toBe(150000);
    });

    it('shows PDF indir, Gönder and, apart, Sil', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(windowAction('downloadQuotePdf', $quote))
            ->assertActionVisible(windowAction('sendQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('acceptQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('rejectQuoteFromWindow', $quote))
            ->assertActionVisible(windowAction('deleteQuoteFromWindow', $quote, RecordActionsSection::DANGER_ACTIONS_KEY));
    });

    it('deletes the draft after confirmation', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->callAction(windowAction('deleteQuoteFromWindow', $quote, RecordActionsSection::DANGER_ACTIONS_KEY))
            ->assertNotified('Taslak silindi.');

        expect(Quote::count())->toBe(0)
            ->and(QuoteItem::count())->toBe(0);
    });
});

describe('window of a quote that was sent', function () {
    it('opens locked', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->mountAction(windowOf($quote))
            ->assertFormFieldIsDisabled('customer_id')
            ->assertFormFieldIsDisabled('notes');
    });

    it('does not let a stale call change a quote that is no longer a draft', function () {
        $quote = windowQuote('draft');
        $test = Livewire::test(ListQuotes::class)->mountAction(windowOf($quote));
        Quote::query()->whereKey($quote->id)->update(['status' => QuoteStatus::Sent]);

        $test->fillForm(['notes' => 'Gizlice'])->callMountedAction();

        expect($quote->fresh()->notes)->not->toBe('Gizlice');
    });

    it('shows PDF indir, Tekrar gönder, Kabul et and Reddet but no Sil', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(windowAction('downloadQuotePdf', $quote))
            ->assertActionVisible(windowAction('sendQuoteFromWindow', $quote))
            ->assertActionVisible(windowAction('acceptQuoteFromWindow', $quote))
            ->assertActionVisible(windowAction('rejectQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('deleteQuoteFromWindow', $quote, RecordActionsSection::DANGER_ACTIONS_KEY));
    });

    it('shows an expired quote only PDF indir and Tekrar gönder', function () {
        $quote = windowQuote('sent', ['valid_until' => '2027-02-28']);

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(windowAction('downloadQuotePdf', $quote))
            ->assertActionVisible(windowAction('sendQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('acceptQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('rejectQuoteFromWindow', $quote));
    });

    it('shows a decided quote only PDF indir', function (string $state) {
        $quote = windowQuote($state);

        Livewire::test(ListQuotes::class)
            ->assertActionVisible(windowAction('downloadQuotePdf', $quote))
            ->assertActionDoesNotExist(windowAction('sendQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('acceptQuoteFromWindow', $quote))
            ->assertActionDoesNotExist(windowAction('rejectQuoteFromWindow', $quote));
    })->with(['accepted', 'rejected']);
});

describe('download', function () {
    it('downloads the PDF of the quote', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->callAction(windowAction('downloadQuotePdf', $quote))
            ->assertFileDownloaded($quote->number.'.pdf');
    });
});

describe('send', function () {
    beforeEach(fn () => Mail::fake());

    it('asks for the address, prefilled, and sends the quote', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('sendQuote')->table($quote))
            ->assertSchemaStateSet(['recipient_email' => 'ayse@example.com'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Teklif ayse@example.com adresine gönderildi.');

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('ayse@example.com'));
        expect($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('sends to another address', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('sendQuote')->table($quote), ['recipient_email' => 'muhasebe@example.com']);

        Mail::assertSent(QuoteMail::class, fn (QuoteMail $mail): bool => $mail->hasTo('muhasebe@example.com'));
        expect($quote->fresh()->sent_to)->toBe('muhasebe@example.com');
    });

    it('needs a valid address', function (?string $address) {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('sendQuote')->table($quote), ['recipient_email' => $address])
            ->assertHasFormErrors(['recipient_email']);

        Mail::assertNothingSent();
    })->with([null, 'gecersiz']);

    it('asks for an address when the contact has none', function () {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->primary()->create(['email' => null]);
        $quote = Quote::factory()->for($customer)->create();
        QuoteItem::factory()->for($quote)->create();

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('sendQuote')->table($quote))
            ->assertSchemaStateSet(['recipient_email' => null])
            ->callMountedAction()
            ->assertHasFormErrors(['recipient_email' => 'required']);
    });

    it('is called "Tekrar gönder" for a quote that was sent before and makes an expired one valid again', function () {
        $quote = windowQuote('sent', ['valid_until' => '2027-02-28']);

        Livewire::test(ListQuotes::class)
            ->assertActionHasLabel(TestAction::make('sendQuote')->table($quote), 'Tekrar gönder')
            ->callAction(TestAction::make('sendQuote')->table($quote))
            ->assertNotified();

        expect($quote->fresh()->isExpired())->toBeFalse()
            ->and($quote->fresh()->valid_until->toDateString())->toBe('2027-03-16');
    });

    it('is called "Gönder" for a draft', function () {
        $quote = windowQuote();

        Livewire::test(ListQuotes::class)
            ->assertActionHasLabel(TestAction::make('sendQuote')->table($quote), 'Gönder');
    });

    it('sends from the quote window', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(windowAction('sendQuoteFromWindow', $quote))
            ->assertNotified('Teklif ayse@example.com adresine gönderildi.');

        Mail::assertSent(QuoteMail::class, 1);
    });

    it('reports a failed send without details and records nothing', function () {
        $quote = windowQuote();
        Mail::shouldReceive('to')->andThrow(new TransportException('smtp://kullanici:parola@sunucu reddetti'));

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('sendQuote')->table($quote))
            ->assertNotified(__('quotes.send_failed'));

        expect($quote->fresh()->status)->toBe(QuoteStatus::Draft)
            ->and($quote->fresh()->sent_at)->toBeNull();
    });
});

describe('accept', function () {
    it('creates the services and the first charges of a customer quote', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('acceptQuote')->table($quote))
            ->assertSchemaStateSet(['start_date' => '2027-03-01'])
            ->assertFormFieldIsHidden('short_name')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Teklif kabul edildi: 1 hizmet oluştu.');

        expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted)
            ->and(CustomerService::query()->sole()->start_date->toDateString())->toBe('2027-03-01')
            ->and(Charge::count())->toBe(1);
    });

    it('takes the chosen start date', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('acceptQuote')->table($quote), ['start_date' => '2027-04-01']);

        expect(CustomerService::query()->sole()->start_date->toDateString())->toBe('2027-04-01');
    });

    it('asks the short name of the new customer of a hand-written recipient, filled with the company', function () {
        $quote = Quote::factory()->sent()->manualRecipient()->create([
            'valid_until' => '2027-03-15',
            'recipient_company' => 'Veli Ltd.',
            'recipient_name' => 'Ali Veli',
        ]);
        QuoteItem::factory()->for($quote)->create();

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('acceptQuote')->table($quote))
            ->assertSchemaStateSet(['short_name' => 'Veli Ltd.'])
            ->assertFormFieldIsVisible('short_name')
            ->fillForm(['short_name' => 'Veli'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        expect(Customer::query()->where('short_name', 'Veli')->exists())->toBeTrue();
    });

    it('fills the short name with the person when there is no company', function () {
        $quote = Quote::factory()->sent()->manualRecipient()->create([
            'valid_until' => '2027-03-15',
            'recipient_company' => null,
            'recipient_name' => 'Ali Veli',
        ]);
        QuoteItem::factory()->for($quote)->create();

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('acceptQuote')->table($quote))
            ->assertSchemaStateSet(['short_name' => 'Ali Veli']);
    });

    it('shows a taken short name under the field and creates nothing', function () {
        Customer::factory()->create(['short_name' => 'Veli Ltd.']);
        $quote = Quote::factory()->sent()->manualRecipient()->create(['valid_until' => '2027-03-15', 'recipient_company' => 'Veli Ltd.']);
        QuoteItem::factory()->for($quote)->create();

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('acceptQuote')->table($quote), ['short_name' => 'Veli Ltd.'])
            ->assertHasFormErrors(['short_name']);

        expect(Customer::count())->toBe(1)
            ->and($quote->fresh()->status)->toBe(QuoteStatus::Sent);
    });

    it('shows a start date whose period has ended under the field', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('acceptQuote')->table($quote), ['start_date' => '2025-01-01'])
            ->assertHasFormErrors(['start_date']);

        expect(CustomerService::count())->toBe(0);
    });

    it('requires a start date', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(TestAction::make('acceptQuote')->table($quote), ['start_date' => null])
            ->assertHasFormErrors(['start_date' => 'required']);
    });

    it('accepts from the quote window', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(windowAction('acceptQuoteFromWindow', $quote))
            ->assertNotified('Teklif kabul edildi: 1 hizmet oluştu.');

        expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted);
    });
});

describe('reject', function () {
    it('asks for confirmation and closes the quote without creating anything', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->mountAction(TestAction::make('rejectQuote')->table($quote))
            ->callMountedAction()
            ->assertNotified('Teklif reddedildi.');

        expect($quote->fresh()->status)->toBe(QuoteStatus::Rejected)
            ->and(CustomerService::count())->toBe(0);
    });

    it('rejects from the quote window', function () {
        $quote = windowQuote('sent');

        Livewire::test(ListQuotes::class)
            ->callAction(windowAction('rejectQuoteFromWindow', $quote))
            ->assertNotified('Teklif reddedildi.');

        expect($quote->fresh()->status)->toBe(QuoteStatus::Rejected);
    });
});
