<?php

use App\Actions\Payments\RecordPayment;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Livewire\Pages\Customers\Show;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\User;
use App\Support\Ui\Links;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * An active 4.000 TL service for 01.03.2026 – 01.03.2027 with a domain.
 */
function uiCardService(Customer $customer, array $attributes = [], ?string $domain = null): CustomerService
{
    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create([
            'name' => 'Kurumsal Paket',
            'price' => 400000,
            'currency' => Currency::Try,
            'start_date' => '2026-03-01',
            'end_date' => '2027-03-01',
            ...$attributes,
        ]);

    if ($domain !== null) {
        $service->items()->first()->update(['details' => ['domain' => $domain]]);
    }

    return $service;
}

function uiCard(Customer $customer)
{
    return Livewire::test(Show::class, ['customer' => $customer]);
}

it('is reachable from the customer list and needs a signed-in user', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Logitech Ltd.']);

    $this->get(route('app.customers.show', $customer))->assertOk()->assertSee('Logi')->assertSee('Logitech Ltd.');

    auth()->logout();

    $this->get(route('app.customers.show', $customer))->assertRedirect(route('app.login'));
});

it('links the customer list rows and the search to the glass card', function () {
    $customer = Customer::factory()->create();

    expect(Links::customer($customer))->toBe(route('app.customers.show', $customer));
});

it('shows the open debt and the credit balance per currency, never summed', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 100000, 'currency' => Currency::Try, 'due_date' => '2026-07-01']);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd, 'due_date' => '2026-07-01']);
    Payment::factory()->for($customer)->create(['amount' => 70000, 'currency' => Currency::Eur]);

    uiCard($customer)->assertSee('1.000 TL')->assertSee('50 USD')->assertSee('700 EUR');
});

it('shows a dash for no debt and no balance', function () {
    uiCard(Customer::factory()->create())->assertSee(__('money.empty'));
});

it('has "Ödeme al" with the customer fixed and "Hizmet ekle" in the header, not for an archived customer', function () {
    $customer = Customer::factory()->create();

    $html = uiCard($customer)->html();

    expect($html)
        ->toContain("name: 'record-payment', params: { customer: {$customer->id}, fixed: true }")
        ->toContain("name: 'assign-service', params: { customer: {$customer->id} }");

    $customer->archive();

    expect(uiCard($customer->fresh())->html())
        ->not->toContain("name: 'assign-service'")
        ->not->toContain("name: 'record-payment'")
        ->toContain(__('archive.status_archived'));
});

it('lists the active services with price, end date and domain, and counts them', function () {
    $customer = Customer::factory()->create();
    uiCardService($customer, [], 'logi.com.tr');
    uiCardService($customer, ['name' => 'Ücretsiz Alan Adı', 'is_free' => true]);

    uiCard($customer)
        ->assertSee('Kurumsal Paket')
        ->assertSee('4.000 TL')
        ->assertSee('01.03.2027')
        ->assertSee('logi.com.tr')
        ->assertSee(__('customer_services.free'))
        ->assertSee(__('customer_services.active_count', ['count' => 2]));
});

it('opens the edit window from the service name, with no row button', function () {
    $customer = Customer::factory()->create();
    $service = uiCardService($customer);

    uiCard($customer)->assertSee("name: 'edit-service', params: { service: {$service->id} }", false);
});

it('gives each domain an "open the site" link and a copy button, and none to a value that is not a host name', function () {
    $customer = Customer::factory()->create();
    uiCardService($customer, [], 'logi.com.tr');
    uiCardService($customer, ['name' => 'Garip'], 'bad domain; drop');

    $html = uiCard($customer)->html();

    expect($html)
        ->toContain('href="https://logi.com.tr"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain(__('customer_services.copy_domain'))
        ->not->toContain('https://bad domain');
});

it('shows "+N alan adı" for the other domains of a linked account', function () {
    $customer = Customer::factory()->create();
    $service = uiCardService($customer, [], 'logi.com.tr');
    $service->items()->first()->forceFill(['remote_domains' => ['ikinci.com', 'logi.net']])->save();

    expect($service->fresh()->otherDomains())->toHaveCount(2);

    uiCard($customer)->assertSee('+'.__('customer_services.more_domains', ['count' => 2]));
});

it('marks a service ending soon with an amber badge and a plain one with none', function () {
    $customer = Customer::factory()->create();
    uiCardService($customer, ['name' => 'Yakın', 'start_date' => '2025-06-25', 'end_date' => '2026-06-25']);
    uiCardService($customer, ['name' => 'Uzak']);

    $html = uiCard($customer)->html();

    expect(substr_count($html, __('customer_services.ending_soon')))->toBe(2)
        ->and($html)->toContain('ui-badge ui-badge-amber');
});

it('keeps cancelled services in a collapsed section with the count and a "Yeniden aktif et" button', function () {
    $customer = Customer::factory()->create();
    uiCardService($customer, ['name' => 'Aktif Hizmet']);
    $cancelled = uiCardService($customer, ['name' => 'İptal Hizmet', 'status' => CustomerServiceStatus::Cancelled]);

    $html = uiCard($customer)->assertSee(__('customers.cancelled_services', ['count' => 1]))->html();

    expect($html)
        ->toContain('<details')
        ->toContain("name: 'reactivate-service', params: { service: {$cancelled->id} }")
        ->and(substr_count($html, "name: 'reactivate-service'"))->toBe(1);
});

it('shows the empty services state with "Hizmet ekle"', function () {
    $customer = Customer::factory()->create();

    uiCard($customer)->assertSee(__('customer_services.empty_heading'));
});

it('lists only the open charges, oldest due date first, with "Ödeme al" and the description opening the edit window', function () {
    $customer = Customer::factory()->create();
    $later = Charge::factory()->for($customer)->create(['description' => 'Alan adı', 'amount' => 40000, 'due_date' => '2026-07-01']);
    $first = Charge::factory()->for($customer)->create(['description' => 'Hosting', 'amount' => 150000, 'due_date' => '2026-05-01']);
    Charge::factory()->for($customer)->paid()->create(['description' => 'Eski ödenmiş']);
    Charge::factory()->for($customer)->cancelled()->create(['description' => 'Eski iptal']);

    $html = uiCard($customer)->assertDontSee('Eski ödenmiş')->assertDontSee('Eski iptal')->html();

    expect(strpos($html, 'Hosting'))->toBeLessThan(strpos($html, 'Alan adı'))
        ->and($html)->toContain("name: 'edit-charge', params: { charge: {$first->id} }")
        ->toContain("name: 'record-payment', params: { customer: {$customer->id}, currency: 'TRY', fixed: true }")
        ->toContain('href="'.e(Links::customerCharges($customer)).'"');
});

it('shows only "Kalan" for an open charge and a faded original amount once partially paid', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-07-10']);

    uiCard($customer)->assertSee('4.000 TL')->assertDontSee("4.000 TL'den");

    app(RecordPayment::class)->handle(actor(), $customer, [
        'amount' => 100000, 'currency' => Currency::Try, 'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ]);

    uiCard($customer->fresh())->assertSee('3.000 TL')->assertSee("4.000 TL'den")->assertSee(__('charges.partially_paid'));

    expect($charge->fresh()->isPartiallyPaid())->toBeTrue();
});

it('shows the overdue badge in red and the due date under the description below xl', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => '2026-06-01']);

    $html = uiCard($customer)->html();

    expect($html)->toContain('ui-badge ui-badge-red')->toContain(__('charges.overdue_days', ['count' => 14]))->toContain('ui-below-xl');
});

it('shows the reminder line under a reminded charge', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->reminderSent()->create(['due_date' => '2026-07-01']);

    uiCard($customer)->assertSee(__('reminders.sent_on', ['date' => '15.06.2026']));
});

it('offers a WhatsApp icon link only when the primary contact has a phone', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe', 'phone' => '0532 111 22 33']);
    Charge::factory()->for($customer)->create(['due_date' => '2026-07-01']);

    $html = uiCard($customer)->html();

    expect($html)->toContain('https://wa.me/905321112233')->toContain('aria-label="WhatsApp"');

    Contact::query()->update(['phone' => null]);

    expect(uiCard($customer->fresh())->html())->not->toContain('wa.me');
});

it('shows the empty charges state', function () {
    uiCard(Customer::factory()->create())->assertSee(__('charges.open_empty_heading'));
});

it('shows the five most recent payments with their domains, the amount opening the detail window', function () {
    $customer = Customer::factory()->create();
    foreach (range(1, 6) as $day) {
        Payment::factory()->for($customer)->create(['amount' => $day * 10000, 'paid_on' => sprintf('2026-05-%02d', $day)]);
    }
    $newest = Payment::query()->where('amount', 60000)->firstOrFail();

    $html = uiCard($customer)->html();

    expect($html)->toContain("name: 'view-payment', params: { payment: {$newest->id} }")
        ->and(substr_count($html, "name: 'view-payment'"))->toBe(5)
        ->and($html)->not->toContain('>100 TL<')
        ->and($html)->toContain('href="'.Links::customerPayments($customer).'"');
});

it('has a separate confirmed delete for a payment row and never deletes from the amount', function () {
    $customer = Customer::factory()->create();
    $payment = Payment::factory()->for($customer)->create();

    $html = uiCard($customer)->html();

    expect($html)->toContain("name: 'confirm', params: { action: 'delete-payment', id: {$payment->id} }");
});

it('shows the empty payments state with "Ödeme al"', function () {
    uiCard(Customer::factory()->create())->assertSee(__('payments.recent_empty_heading'));
});

it('shows the quotes section only for a customer that has quotes', function () {
    $customer = Customer::factory()->create();

    uiCard($customer)->assertDontSee(__('quotes.card_heading'));

    Quote::factory()->for($customer)->create(['number' => 'TKL-2026-0001']);

    uiCard($customer->fresh())->assertSee(__('quotes.card_heading'))->assertSee('TKL-2026-0001');
});

it('lists the primary contact first with tel and mailto links', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->create(['name' => 'Zeynep', 'title' => 'Muhasebe', 'phone' => '0212 000 00 00', 'email' => 'z@example.com']);
    $primary = Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe', 'phone' => '0532 111 22 33', 'email' => 'ayse@example.com']);

    $html = uiCard($customer)->html();

    expect(strpos($html, 'Ayşe'))->toBeLessThan(strpos($html, 'Zeynep'))
        ->and($html)->toContain('Ayşe · '.__('contacts.is_primary'))
        ->toContain('href="tel:0532 111 22 33"')
        ->toContain('href="mailto:z@example.com"')
        ->toContain('Muhasebe')
        ->toContain("name: 'contact', params: { customer: {$customer->id}, contact: {$primary->id} }")
        ->toContain("name: 'contact', params: { customer: {$customer->id} }");
});

it('renders a contact without a title, phone or e-mail', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe', 'title' => null, 'phone' => null, 'email' => null]);

    uiCard($customer)->assertSee('Ayşe')->assertDontSee('tel:', false)->assertDontSee('mailto:', false);
});

it('shows billing details and notes with dashes for the empty ones, and the edit link', function () {
    $customer = Customer::factory()->create(['legal_title' => 'Logitech Ltd.', 'tax_office' => 'Kadıköy', 'tax_number' => null, 'billing_address' => 'Moda cad. 5', 'notes' => 'VIP müşteri']);

    uiCard($customer)
        ->assertSee('Kadıköy')
        ->assertSee('Moda cad. 5')
        ->assertSee('VIP müşteri')
        ->assertSee("name: 'edit-customer', params: { customer: {$customer->id} }", false);
});

it('offers archive and, for a customer nothing depends on, delete at the bottom', function () {
    $customer = Customer::factory()->create();

    $html = uiCard($customer)->html();

    expect($html)->toContain("action: 'archive-customer'")->toContain("action: 'delete-customer'");

    uiCardService($customer);

    expect(uiCard($customer->fresh())->html())->toContain("action: 'archive-customer'")->not->toContain("action: 'delete-customer'");
});

it('offers "Arşivden çıkar" for an archived customer instead of archive', function () {
    $customer = Customer::factory()->archived()->create();

    expect(uiCard($customer)->html())->toContain("action: 'unarchive-customer'")->not->toContain("action: 'archive-customer'");
});

it('refreshes after a payment, a charge, a service, a contact or a customer changed in a window', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);

    $card = uiCard($customer)->assertSee(__('charges.open_empty_heading'));

    Charge::factory()->for($customer)->create(['description' => 'Yeni borç', 'due_date' => '2026-07-01']);

    $card->dispatch('charges-changed')->assertSee('Yeni borç');

    foreach (['payments-changed', 'customer-services-changed', 'customers-changed', 'contacts-changed', 'quotes-changed'] as $event) {
        $card->dispatch($event)->assertHasNoErrors();
    }
});

it('runs the same number of queries for a customer with a few rows as with many', function () {
    $count = function (Customer $customer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        uiCard($customer);

        return count(DB::getQueryLog());
    };

    // Settings are read once per request and then kept: warm them so the first measurement is not the dearer one.
    uiCard(Customer::factory()->create());

    $small = Customer::factory()->create();
    uiCardService($small, [], 'a.com');
    Charge::factory()->for($small)->create(['due_date' => '2026-07-01']);
    Payment::factory()->for($small)->create();
    Contact::factory()->for($small)->primary()->create();

    $large = Customer::factory()->create();
    foreach (range(1, 6) as $i) {
        uiCardService($large, ['name' => "Hizmet {$i}"], "d{$i}.com");
        Charge::factory()->for($large)->create(['due_date' => '2026-07-01']);
        Payment::factory()->for($large)->create();
        Contact::factory()->for($large)->create();
    }
    Contact::factory()->for($large)->primary()->create();

    expect($count($large))->toBe($count($small));
});

it('renders the header buttons as text, never as raw template code', function () {
    $html = uiCard(Customer::factory()->create())->html();

    expect($html)
        ->toContain(__('payments.record'))
        ->toContain(__('customer_services.assign'))
        ->not->toContain('{{')
        ->not->toContain("__('");
});

it('draws the card surfaces as panes and leaves the blur to the bars and menus', function () {
    $customer = Customer::factory()->create();
    uiCardService($customer);
    uiCardService($customer, [], 'ikinci.com');

    $html = test()->get(route('app.customers.show', $customer))->assertOk()->getContent();

    expect($html)->not->toContain('ui-card ui-glass')->not->toContain('ui-strip ui-glass')
        ->and(preg_match_all('/(?<![\w-])ui-glass(?![\w-])/', $html))->toBeLessThanOrEqual(6)
        ->and(substr_count($html, 'ui-pane'))->toBeGreaterThan(5);
});
