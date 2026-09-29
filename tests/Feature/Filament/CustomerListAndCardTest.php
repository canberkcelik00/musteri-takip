<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

// --- Liste --------------------------------------------------------------

it('shows the overdue customers first, then by short name', function () {
    $this->travelTo('2026-06-10 12:00:00');

    $ontime = Customer::factory()->create(['short_name' => 'Ahmet']);
    Charge::factory()->for($ontime)->create(['due_date' => '2026-07-01']);

    $overdueB = Customer::factory()->create(['short_name' => 'Zeynep']);
    Charge::factory()->for($overdueB)->create(['due_date' => '2026-06-01']);

    $overdueA = Customer::factory()->create(['short_name' => 'Bora']);
    Charge::factory()->for($overdueA)->create(['due_date' => '2026-06-01']);

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$overdueA, $overdueB, $ontime], inOrder: true);
});

it('shows the short name bold with the legal title as description', function () {
    $customer = Customer::factory()->create(['short_name' => 'Bora', 'legal_title' => 'Bora Ticaret Ltd. Şti.']);

    Livewire::test(ListCustomers::class)
        ->assertSee('Bora Ticaret Ltd. Şti.');
});

it('shows the open debt in red for a customer with an overdue charge', function () {
    $this->travelTo('2026-06-10 12:00:00');

    $overdue = Customer::factory()->create();
    Charge::factory()->for($overdue)->create(['due_date' => '2026-06-01']);

    expect(Livewire::test(ListCustomers::class)->html())->toContain('fi-color-danger');
});

it('shows the open debt of each currency separately in the list, never summed', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 200000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd]);

    Livewire::test(ListCustomers::class)
        ->assertSeeInOrder(['2.000 TL', '50 USD']);
});

it('has only "Ödeme al" as the row action, hidden for an archived customer', function () {
    $customer = Customer::factory()->create();
    $archived = Customer::factory()->archived()->create();

    Livewire::test(ListCustomers::class)
        ->assertActionExists(TestAction::make('recordPayment')->table($customer))
        ->assertTableActionDoesNotExist('edit', null, $customer)
        ->assertTableActionDoesNotExist('archive', null, $customer)
        ->assertTableActionDoesNotExist('delete', null, $customer)
        ->filterTable('archived', true)
        ->assertActionHidden(TestAction::make('recordPayment')->table($archived));
});

it('opens the payment form from the list row with the customer selected but still changeable', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();

    Livewire::test(ListCustomers::class)
        ->mountAction(TestAction::make('recordPayment')->table($customer))
        ->assertSchemaStateSet(['customer_id' => $customer->id])
        ->fillForm(['customer_id' => $other->id])
        ->assertSchemaStateSet(['customer_id' => $other->id]);
});

// --- Kart: şerit ve para birimleri ---------------------------------------

it('shows the strip totals per currency, never summed across currencies', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 200000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd]);
    Payment::factory()->for($customer)->create(['amount' => 60000, 'currency' => Currency::Try]);
    Payment::factory()->for($customer)->create(['amount' => 2500, 'currency' => Currency::Usd]);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertSeeInOrder(['Açık borç', '2.000 TL', '50 USD'])
        ->assertSeeInOrder(['Alacak bakiyesi', '600 TL', '25 USD']);
});

// --- Kart: hizmet alan adı sütunu ----------------------------------------

it("shows the unique domain values of all of a service's items, joined with commas", function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();
    $service->items()->delete();
    CustomerServiceItem::factory()->for($service, 'customerService')->create([
        'type' => ServiceType::Hosting,
        'details' => ['domain' => 'logi.com.tr'],
    ]);
    CustomerServiceItem::factory()->for($service, 'customerService')->create([
        'type' => ServiceType::Domain,
        'details' => ['domain' => 'logi.com.tr'],
    ]);
    CustomerServiceItem::factory()->for($service, 'customerService')->create([
        'type' => ServiceType::Email,
        'details' => ['domain' => 'mail.logi.com.tr'],
    ]);

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertTableColumnStateSet('domain', 'logi.com.tr, mail.logi.com.tr', $service);
});

// --- Kart: son 5 ödeme ----------------------------------------------------

it('shows only the 5 most recent payments on the card', function () {
    $customer = Customer::factory()->create();
    $payments = collect(range(1, 7))->map(fn (int $day): Payment => Payment::factory()->for($customer)->create(['paid_on' => "2026-01-0{$day}"]));

    $newest5 = $payments->sortByDesc('paid_on')->take(5);
    $oldest2 = $payments->sortBy('paid_on')->take(2);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords($newest5)
        ->assertCanNotSeeTableRecords($oldest2);
});

// --- Kart: kişiler tel/mailto --------------------------------------------

it('links the contact phone and email as tel: and mailto:', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['phone' => '0532 412 18 90', 'email' => 'murat@example.com']);

    $html = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->html();

    expect($html)->toContain('tel:0532 412 18 90')
        ->and($html)->toContain('mailto:murat@example.com');
});

// --- "Tüm borçlar" / "Tüm ödemeler" bağlantıları --------------------------

it('has a "Tüm borçlar" link on the card that opens the customer\'s full, unfiltered charge history, including an archived customer', function () {
    $customer = Customer::factory()->create();
    $archived = Customer::factory()->archived()->create();

    $openCharge = Charge::factory()->for($customer)->create(['description' => 'Açık borç']);
    $paidCharge = Charge::factory()->for($customer)->paid()->create(['description' => 'Ödenmiş borç']);
    $otherCharge = Charge::factory()->create(['description' => 'Başka müşteri borcu']);
    $archivedCharge = Charge::factory()->for($archived)->create(['description' => 'Arşivli müşteri borcu']);

    Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertActionExists(TestAction::make('viewAll')->table());

    $url = ChargeResource::getUrl('index', ['filters' => [
        'customer' => ['value' => $customer->id],
        'status' => ['value' => ''],
    ]]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Açık borç')
        ->assertSee('Ödenmiş borç')
        ->assertDontSee('Başka müşteri borcu');

    $archivedUrl = ChargeResource::getUrl('index', ['filters' => [
        'customer' => ['value' => $archived->id],
        'status' => ['value' => ''],
    ]]);

    $this->get($archivedUrl)
        ->assertOk()
        ->assertSee('Arşivli müşteri borcu');
});

it('has a "Tüm ödemeler" link on the card that opens the customer\'s full payment history, including an archived customer', function () {
    $customer = Customer::factory()->create();
    $archived = Customer::factory()->archived()->create();

    $ownPayment = Payment::factory()->for($customer)->create(['note' => 'Kendi ödemesi']);
    $otherPayment = Payment::factory()->create(['note' => 'Başka müşteri ödemesi']);
    $archivedPayment = Payment::factory()->for($archived)->create(['note' => 'Arşivli müşteri ödemesi']);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertActionExists(TestAction::make('viewAll')->table());

    $url = PaymentResource::getUrl('index', ['filters' => ['customer' => ['value' => $customer->id]]]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Kendi ödemesi')
        ->assertDontSee('Başka müşteri ödemesi');

    $archivedUrl = PaymentResource::getUrl('index', ['filters' => ['customer' => ['value' => $archived->id]]]);

    $this->get($archivedUrl)
        ->assertOk()
        ->assertSee('Arşivli müşteri ödemesi');
});

// --- Kart: karttan hizmet ekleme (kabul ölçütü) ---------------------------

it('assigns a service from the card in one click + selection + one click', function () {
    $customer = Customer::factory()->create();
    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => Money::parseAmount('4.000'), 'currency' => Currency::Try]);
    $package->catalogServices()->attach(CatalogService::factory()->ofType(ServiceType::Hosting)->create()->id);

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->mountAction('assignService')
        ->fillForm(['sold' => "package:{$package->id}"])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($customer->customerServices()->count())->toBe(1)
        ->and($customer->charges()->sole()->amount)->toBe(Money::parseAmount('4.000'));
});

it('warns and assigns nothing for an archived customer from the card', function () {
    $customer = Customer::factory()->archived()->create();
    $catalogService = CatalogService::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->mountAction('assignService')
        ->fillForm(['sold' => "catalog_service:{$catalogService->id}"])
        ->callMountedAction()
        ->assertNotified('Önce müşteriyi arşivden çıkarın.');

    expect($customer->customerServices()->count())->toBe(0);
});
