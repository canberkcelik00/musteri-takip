<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Livewire\Pages\Customers\Index;
use App\Livewire\Topbar\Search;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Logi's hosting sells under logi.com.tr (docs/UI.md §7's example).
 */
function logiSellingUnderDomain(): Customer
{
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logitech Bilgisayar Ürünleri Ltd. Şti.']);

    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create();

    $service->items()->first()->update([
        'details' => ['domain' => 'logi.com.tr', 'server' => 'srv14.natro.com'],
    ]);

    return $customer;
}

/**
 * Whether the values first appear in the HTML in the order given (Livewire's own assertSeeInOrder reads the
 * JSON-escaped response, which cannot match non-ASCII text).
 *
 * @param  list<string>  $values
 */
function appearInOrder(string $html, array $values): bool
{
    $positions = array_map(fn (string $value) => strpos($html, $value), $values);

    return ! in_array(false, $positions, true) && $positions === collect($positions)->sort()->values()->all();
}

// --- Liste --------------------------------------------------------------

it('is reachable from the sidebar and needs a signed-in user', function () {
    $this->get(route('app.customers.index'))->assertOk()->assertSee(__('customers.plural'));

    auth()->logout();

    $this->get(route('app.customers.index'))->assertRedirect(route('app.login'));
});

it('shows the overdue customers first, then by short name', function () {
    $this->travelTo('2026-06-10 12:00:00');

    $ontime = Customer::factory()->create(['short_name' => 'Ahmet']);
    Charge::factory()->for($ontime)->create(['due_date' => '2026-07-01']);

    $overdueB = Customer::factory()->create(['short_name' => 'Zeynep']);
    Charge::factory()->for($overdueB)->create(['due_date' => '2026-06-01']);

    $overdueA = Customer::factory()->create(['short_name' => 'Bora']);
    Charge::factory()->for($overdueA)->create(['due_date' => '2026-06-01']);

    Livewire::test(Index::class)->assertSeeInOrder(['Bora', 'Zeynep', 'Ahmet']);
});

it('shows the short name with the legal title under it', function () {
    Customer::factory()->create(['short_name' => 'Bora', 'legal_title' => 'Bora Ticaret Ltd. Şti.']);

    Livewire::test(Index::class)->assertSee('Bora')->assertSee('Bora Ticaret Ltd. Şti.');
});

it('shows the open debt in red for a customer with an overdue charge and plain otherwise', function () {
    $this->travelTo('2026-06-10 12:00:00');

    $late = Customer::factory()->create(['short_name' => 'Geç']);
    Charge::factory()->for($late)->create(['amount' => 150000, 'due_date' => '2026-06-01']);
    $fine = Customer::factory()->create(['short_name' => 'Düzenli']);
    Charge::factory()->for($fine)->create(['amount' => 90000, 'due_date' => '2026-07-01']);

    $html = Livewire::test(Index::class)->html();

    expect($html)->toContain('ui-money ui-money-danger')->toContain('1.500 TL')->toContain('900 TL');
    expect(substr_count($html, 'ui-money-danger'))->toBe(1);
});

it('shows the open debt of each currency separately, never summed', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 100000, 'currency' => Currency::Try, 'due_date' => '2026-07-01']);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd, 'due_date' => '2026-07-01']);

    Livewire::test(Index::class)->assertSee('1.000 TL')->assertSee('50 USD');
});

it('shows a dash for a customer without debt or an active service', function () {
    Customer::factory()->create();

    Livewire::test(Index::class)->assertSee(__('money.empty'));
});

it('hides archived customers by default and shows them on request', function () {
    Customer::factory()->create(['short_name' => 'Aktif Firma']);
    Customer::factory()->archived()->create(['short_name' => 'Eski Firma']);

    Livewire::test(Index::class)
        ->assertSee('Aktif Firma')
        ->assertDontSee('Eski Firma')
        ->set('archive', Index::ARCHIVE_ARCHIVED)
        ->assertDontSee('Aktif Firma')
        ->assertSee('Eski Firma')
        ->assertSee(__('archive.status_archived'))
        ->set('archive', Index::ARCHIVE_ALL)
        ->assertSee('Aktif Firma')
        ->assertSee('Eski Firma');
});

it('has "Ödeme al" as the only row action, not for an archived customer', function () {
    Customer::factory()->create(['short_name' => 'Aktif Firma']);

    $html = Livewire::test(Index::class)->html();

    expect(substr_count($html, "name: 'record-payment'"))->toBe(1);

    Livewire::test(Index::class)->set('archive', Index::ARCHIVE_ARCHIVED)->assertDontSee("name: 'record-payment'", false);

    Customer::query()->update(['archived_at' => now()]);

    expect(Livewire::test(Index::class)->set('archive', Index::ARCHIVE_ALL)->html())->not->toContain("name: 'record-payment'");
});

it('opens the payment window for the row\'s customer', function () {
    $customer = Customer::factory()->create();

    Livewire::test(Index::class)->assertSee("params: { customer: {$customer->id} }", false);
});

it('makes the whole row open the customer card', function () {
    $customer = Customer::factory()->create();

    Livewire::test(Index::class)->assertSee('data-href="'.route('app.customers.show', $customer).'"', false);
});

it('shows only customers whose renewal is near, or that await a free decision, when the filters are on', function () {
    $this->travelTo('2026-06-10 12:00:00');

    $soon = Customer::factory()->create(['short_name' => 'Yakın']);
    CustomerService::factory()->for($soon)->create(['start_date' => '2025-06-15', 'end_date' => '2026-06-15']);
    $far = Customer::factory()->create(['short_name' => 'Uzak']);
    CustomerService::factory()->for($far)->create(['start_date' => '2025-12-01', 'end_date' => '2026-12-01']);

    Livewire::test(Index::class)
        ->assertSee('Yakın')->assertSee('Uzak')
        ->call('toggleRenewalSoon')
        ->assertSet('renewalSoon', true)
        ->assertSee('Yakın')->assertDontSee('Uzak')
        ->call('toggleRenewalSoon')
        ->assertSee('Uzak');
});

it('searches by short name, legal title, contact and domain', function () {
    logiSellingUnderDomain();
    $other = Customer::factory()->create(['short_name' => 'Başka Müşteri']);
    Contact::factory()->for($other)->create(['name' => 'Mehmet Kaya']);

    Livewire::test(Index::class)
        ->set('search', 'logi.com.tr')->assertSee('Logi')->assertDontSee('Başka Müşteri')
        ->set('search', 'mehmet')->assertSee('Başka Müşteri')->assertDontSee('Logi')
        ->set('search', 'zzz')->assertSee(__('ui.table.no_results'));
});

it('sorts by the chosen column and ignores a column that cannot be sorted', function () {
    Customer::factory()->create(['short_name' => 'Bir']);
    Customer::factory()->create(['short_name' => 'Çok']);

    Livewire::test(Index::class)
        ->call('sortBy', 'short_name')
        ->assertSet('direction', 'asc')
        ->tap(fn ($component) => expect(appearInOrder($component->html(), ['Bir', 'Çok']))->toBeTrue())
        ->call('sortBy', 'short_name')
        ->assertSet('direction', 'desc')
        ->tap(fn ($component) => expect(appearInOrder($component->html(), ['Çok', 'Bir']))->toBeTrue())
        ->call('sortBy', 'password')
        ->assertSet('sort', 'short_name');
});

it('keeps search, sort and filters in the address', function () {
    Customer::factory()->create(['short_name' => 'Logi']);

    $this->get(route('app.customers.index', ['q' => 'logi', 'sort' => 'short_name', 'direction' => 'desc']))
        ->assertOk()
        ->assertSee('Logi');
});

it('pages the list', function () {
    Customer::factory()->count(30)->sequence(fn ($sequence) => ['short_name' => sprintf('Firma %02d', $sequence->index)])->create();

    Livewire::test(Index::class)
        ->assertSee('Firma 00')
        ->assertDontSee('Firma 29')
        ->call('nextPage')
        ->assertSee('Firma 29')
        ->assertDontSee('Firma 00');
});

it('shows the empty state with a "Yeni müşteri" button when there is no customer at all', function () {
    Livewire::test(Index::class)
        ->assertSee(__('customers.empty_heading'))
        ->assertSee("name: 'create-customer'", false);
});

it('counts the customers in the subtitle', function () {
    Customer::factory()->count(3)->create();

    Livewire::test(Index::class)->assertSee(__('customers.count', ['count' => 3]));
});

it('hides the renewal and count columns below md and lg and keeps name and debt', function () {
    Customer::factory()->create();

    $html = Livewire::test(Index::class)->html();

    expect($html)->toContain('ui-from-md')->toContain('ui-from-lg');
    expect($html)->not->toContain('<th scope="col" class="ui-from-md">'.__('customers.short_name'));
});

it('runs the same number of queries for a few customers as for many', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(Index::class);

        return count(DB::getQueryLog());
    };

    foreach (range(1, 3) as $i) {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->primary()->create();
        Charge::factory()->for($customer)->create();
        CustomerService::factory()->for($customer)->create();
    }

    $few = $count();

    foreach (range(1, 20) as $i) {
        $customer = Customer::factory()->create();
        Contact::factory()->for($customer)->primary()->create();
        Charge::factory()->for($customer)->create(['currency' => Currency::Usd]);
        CustomerService::factory()->for($customer)->create();
    }

    expect($count())->toBe($few);
});

// --- Ctrl+K arama ---------------------------------------------------------

it('finds the customer by the domain name case insensitively and shows its title and domain', function () {
    logiSellingUnderDomain();
    Customer::factory()->create(['short_name' => 'Başka Müşteri']);

    Livewire::test(Search::class)
        ->set('query', 'LOGI.COM.TR')
        ->assertSee('Logi')
        ->assertSee('Logitech Bilgisayar Ürünleri Ltd. Şti.')
        ->assertSee('logi.com.tr')
        ->assertDontSee('Başka Müşteri');
});

it('finds a customer by short name, legal title or contact, with several words treated as AND', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logitech Ltd.']);
    Contact::factory()->for($customer)->create(['name' => 'Ayşe Yılmaz', 'email' => 'ayse@logi.com']);
    Customer::factory()->create(['short_name' => 'Başka']);

    Livewire::test(Search::class)
        ->set('query', 'logitech')->assertSee('Logi')->assertDontSee('Başka')
        ->set('query', 'ayse logi')->assertSee('Logi')
        ->set('query', 'ayse başka')->assertSee(__('ui.search.empty'));
});

it('searches nothing for an empty term', function () {
    Customer::factory()->create(['short_name' => 'Logi']);

    expect(Livewire::test(Search::class)->set('query', '  ')->instance()->results)->toHaveCount(0);
});

it('includes an archived customer, labelled, without the "Ödeme al" shortcut', function () {
    Customer::factory()->archived()->create(['short_name' => 'Eski Firma']);

    $html = Livewire::test(Search::class)->set('query', 'eski')->assertSee('Eski Firma')->assertSee(__('archive.status_archived'))->html();

    expect($html)->not->toContain("name: 'record-payment'");
});

it('offers "Ödeme al" for a customer that opens the payment window with this customer', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::test(Search::class)
        ->set('query', 'logi')
        ->assertSee("name: 'record-payment', params: { customer: {$customer->id} }", false);
});

it('links the result to the customer card', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    Livewire::test(Search::class)
        ->set('query', 'logi')
        ->assertSee('href="'.route('app.customers.show', $customer).'"', false);
});

it('shows up to three distinct domains in the result details', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    foreach (['a.com', 'b.com', 'c.com', 'd.com'] as $domain) {
        $service = CustomerService::factory()->for($customer)->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))->create();
        $service->items()->first()->update(['details' => ['domain' => $domain]]);
    }

    Livewire::test(Search::class)
        ->set('query', 'logi')
        ->assertSee('a.com, b.com, c.com')
        ->assertDontSee('d.com');
});

it('keeps catalog services, packages and payment methods out of the search', function () {
    CatalogService::factory()->create(['name' => 'Süper Hosting']);

    Livewire::test(Search::class)->set('query', 'süper')->assertSee(__('ui.search.empty'));
});

it('is on every page, bound to Ctrl+K and ⌘K, with the narrow-screen "Ara" and "Kapat" buttons', function () {
    $html = $this->get(route('app.today'))->getContent();

    expect($html)
        ->toContain('keydown.window.ctrl.k')
        ->toContain('keydown.window.meta.k')
        ->toContain('ui-search-open')
        ->toContain('ui-search-close')
        ->toContain(__('navigation.search_open'))
        ->toContain(__('navigation.search_close'));
});

// --- Üst çubuk --------------------------------------------------------------

it('has "Ödeme al" as the one primary button and "Yeni müşteri" as a secondary one, hidden on narrow screens', function () {
    $html = $this->get(route('app.today'))->getContent();

    expect(substr_count($html, 'ui-btn-primary'))->toBe(1)
        ->and($html)->toContain('ui-topbar-new')
        ->and($html)->not->toContain('ui-btn-primary ui-topbar-new');
});
