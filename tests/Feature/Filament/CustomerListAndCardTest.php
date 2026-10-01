<?php

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\CancelledCustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Support\RecordActionsSection;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
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

/**
 * Attaches items with the given domains (blank-filtered, deduplication and joining happens in
 * CustomerService::domains(), see the assertions) to a service, replacing its factory-made ones.
 */
function withDomainItems(CustomerService $service, array $domains): CustomerService
{
    $service->items()->delete();

    foreach ($domains as $domain) {
        CustomerServiceItem::factory()->for($service, 'customerService')->create([
            'type' => ServiceType::Domain,
            'details' => ['domain' => $domain],
        ]);
    }

    return $service;
}

it("shows a service's unique domain values as a faded line under its name, on the active table", function () {
    $customer = Customer::factory()->create();
    $service = withDomainItems(CustomerService::factory()->for($customer)->create(), ['logi.com.tr', 'logi.com.tr', 'mail.logi.com.tr']);

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertTableColumnHasDescription('name', 'logi.com.tr, mail.logi.com.tr', $service);
});

it("shows a cancelled service's domains the same way, on the collapsed section's table", function () {
    $customer = Customer::factory()->create();
    $service = withDomainItems(CustomerService::factory()->for($customer)->cancelled()->create(), ['logi.com.tr']);

    $html = Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('name')
        ->record($service)->getDescriptionBelow()->toHtml();

    expect($html)->toContain('logi.com.tr');
});

it("shows a charge's linked service's domains as a faded line under its description, and none for a manual charge", function () {
    $customer = Customer::factory()->create();
    $service = withDomainItems(CustomerService::factory()->for($customer)->create(), ['logi.com.tr']);
    $serviceCharge = Charge::factory()->forCustomerService($service)->create();
    $manualCharge = Charge::factory()->for($customer)->create();

    $column = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('description');

    expect($column->record($serviceCharge)->getDescriptionBelow()->toHtml())->toContain('logi.com.tr')
        ->and($column->record($manualCharge)->getDescriptionBelow()->toHtml())->not->toContain('logi.com.tr');
});

it("shows the unique domains of the services behind a payment's allocated charges, and none without allocations", function () {
    $customer = Customer::factory()->create();
    $serviceA = withDomainItems(CustomerService::factory()->for($customer)->create(), ['logi.com.tr']);
    $serviceB = withDomainItems(CustomerService::factory()->for($customer)->create(), ['logi.com.tr', 'mail.logi.com.tr']);
    $chargeA = Charge::factory()->forCustomerService($serviceA)->create();
    $chargeB = Charge::factory()->forCustomerService($serviceB)->create();

    $payment = Payment::factory()->for($customer)->create();
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $chargeA->id, 'amount' => $chargeA->amount]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $chargeB->id, 'amount' => $chargeB->amount]);

    $noAllocationPayment = Payment::factory()->for($customer)->create();

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertTableColumnHasDescription('amount', 'logi.com.tr, mail.logi.com.tr', $payment)
        ->assertTableColumnHasDescription('amount', null, $noAllocationPayment);
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

it('links the contact phone and email as tel: and mailto:, in a single stacked column', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Murat Kaya', 'title' => 'Bilgi İşlem Sorumlusu', 'phone' => '0532 412 18 90', 'email' => 'murat@example.com']);

    $html = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->html();

    expect($html)->toContain('tel:0532 412 18 90')
        ->and($html)->toContain('mailto:murat@example.com')
        ->and($html)->toContain('Bilgi İşlem Sorumlusu');
});

it('renders a contact without a title, phone or email without error', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe Yılmaz', 'title' => null, 'phone' => null, 'email' => null]);

    Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertOk()
        ->assertSee('Ayşe Yılmaz');
});

it('labels contact creation "Kişi ekle" as a secondary (gray) button', function () {
    $customer = Customer::factory()->create();

    Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertActionHasLabel(TestAction::make('create')->table(), 'Kişi ekle')
        ->assertActionHasColor(TestAction::make('create')->table(), 'gray');
});

it('shows the page title as just the short name, with the legal title as subheading', function () {
    $customer = Customer::factory()->create(['short_name' => 'Deniz Lojistik', 'legal_title' => 'Deniz Lojistik Ltd. Şti.']);

    $page = Livewire::test(ViewCustomer::class, ['record' => $customer->id]);

    expect($page->instance()->getTitle())->toBe('Deniz Lojistik')
        ->and($page->instance()->getSubheading())->toBe('Deniz Lojistik Ltd. Şti.');
});

// --- Kart: açık borçlarda yalnız "Kalan" ----------------------------------

it('shows only "Kalan" for an open charge, and a faded "/ total" line under it once partially paid', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $open = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-07-10']);
    $partial = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-07-10']);
    PaymentAllocation::factory()->create(['charge_id' => $partial->id, 'amount' => 100000]);

    $manager = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertTableColumnDoesNotExist('amount');

    $remaining = $manager->instance()->getTable()->getColumn('remaining');

    expect($remaining->record($open)->getDescriptionBelow())->toBeNull();
    expect($remaining->record($partial)->getDescriptionBelow()->toHtml())->toContain('/ 4.000 TL');
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

// --- Duyarlı sütunlar (375/768/1024/1280 px'te yatay scroll yok) ---------

it('always shows the identity and amount/status columns of the customer list, and hides the rest below md/lg', function () {
    $table = Livewire::test(ListCustomers::class)->instance()->getTable();

    expect($table->getColumn('short_name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('open_debt')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('next_renewal_on')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('active_services_count')->getVisibleFrom())->toBe('lg')
        ->and($table->getColumn('archived_at')->getVisibleFrom())->toBe('md');
});

it('always shows name and price; below md the desktop status column hides, unchanged at md+', function () {
    $customer = Customer::factory()->create();
    $table = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('price')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('status')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('end_date')->getVisibleFrom())->toBe('md');
});

it('applies the same md split to status on the cancelled services table', function () {
    $customer = Customer::factory()->create();
    $table = Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('name')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('status')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('end_date')->getVisibleFrom())->toBe('md');
});

it('always shows description and remaining, which carries the state badge; there is no status column, and the due date column shows from xl', function () {
    $customer = Customer::factory()->create();
    $table = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('description')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('remaining')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('status'))->toBeNull()
        ->and($table->getColumn('due_date')->getVisibleFrom())->toBe('xl');
});

it('renders the due date as dd.mm.yyyy under the charge description, below the xl column', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['description' => 'Yıllık hosting', 'due_date' => '2026-03-05']);

    $table = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('description')->record($charge)->getDescriptionBelow()->toHtml())
        ->toContain('05.03.2026');
});

it('keeps the classic table — real column headers, no card/stacked layout — for services, cancelled services, open charges and payments at every width', function () {
    $customer = Customer::factory()->create();

    foreach ([
        CustomerServicesRelationManager::class,
        CancelledCustomerServicesRelationManager::class,
        ChargesRelationManager::class,
        PaymentsRelationManager::class,
    ] as $managerClass) {
        $table = Livewire::test($managerClass, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

        // Filament switches the whole table to a header-less "content layout" the moment any
        // Stack/Split/Grid column is used, at every width, not just below md (confirmed from
        // source and by reproducing it as a regression) — so these tables use none of those.
        expect($table->hasColumnsLayout())->toBeFalse();
    }

    // Column headers themselves are only ever hidden by the classic per-column visibleFrom/
    // hiddenFrom CSS below md; the <thead> row and its labels are always in the markup.
    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertSee(__('customer_services.end_date'))
        ->assertSee(__('customer_services.price'))
        ->assertSee(__('customer_services.status'));

    Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->assertSee(__('charges.due_date'))
        ->assertSee(__('charges.remaining'));
});

it('always shows the date and amount columns of the recent payments table, and hides method/note below md/lg', function () {
    $customer = Customer::factory()->create();
    $table = Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('paid_on')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('amount')->getVisibleFrom())->toBeNull()
        ->and($table->getColumn('paymentMethod.name')->getVisibleFrom())->toBe('md')
        ->and($table->getColumn('note')->getVisibleFrom())->toBe('lg');
});

it('lays out contacts as a single stacked column, with no separate is_primary column to widen the row', function () {
    $customer = Customer::factory()->create();
    $table = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->instance()->getTable();

    expect($table->getColumn('is_primary'))->toBeNull()
        ->and($table->getColumn('name'))->not->toBeNull();
});

// --- Duyarlılık: md altında satır eylemleri gizli, ilgili panel satırdan açılır -----

const HIDDEN_BELOW_MD = ['class' => 'fi-hidden-below-md'];

it('hides the customer list\'s "Ödeme al" below md; the row already opens the card', function () {
    $table = Livewire::test(ListCustomers::class)->instance()->getTable();

    expect($table->getAction('recordPayment')->getExtraAttributes())->toBe(HIDDEN_BELOW_MD);
});

it('hides the services row actions below md; the name opens "Düzenle" with the rare actions in its "Hizmet işlemleri" section', function () {
    $customer = Customer::factory()->create();
    $active = CustomerService::factory()->for($customer)->create();
    $cancelled = CustomerService::factory()->for($customer)->cancelled()->create();

    $manager = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class]);
    $table = $manager->instance()->getTable();

    expect($table->getAction('editService')->getExtraAttributes())->toBe(HIDDEN_BELOW_MD)
        ->and($table->getAction('reactivate')->getExtraAttributes())->toBe(HIDDEN_BELOW_MD);

    // md altında: ada tıklamak "Düzenle"yi açar (editServiceMobile — satır eylemiyle aynı ad
    // olsaydı ikisi tablonun eylem önbelleğinde çakışırdı); nadir işlemler o panelin altında.
    $cancelOnMobile = sectionAction(TestAction::make('editServiceMobile')->table($active), 'cancelService', danger: true);

    $manager
        ->mountAction($cancelOnMobile)
        ->assertActionMounted($cancelOnMobile);

    // Reactivate yalnızca iptal edilmiş kayıtlarda görünür, yani gerçekte bu satır İptal
    // edilenler tablosundan (CancelledCustomerServicesRelationManager) tetiklenir.
    Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->mountAction(sectionAction(TestAction::make('editServiceMobile')->table($cancelled), 'reactivate'))
        ->assertActionMounted(sectionAction(TestAction::make('editServiceMobile')->table($cancelled), 'reactivate'));
});

it('deletes a service without a charge from the mobile edit panel\'s "Hizmet işlemleri" section', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();

    Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->mountAction(sectionAction(TestAction::make('editServiceMobile')->table($service), 'delete', danger: true))
        ->callMountedAction()
        ->assertNotified();

    expect(CustomerService::query()->whereKey($service->id)->exists())->toBeFalse();
});

it('adds a mobile-only status badge to the name description only for a cancelled or ending-soon service, never for a plain active one', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $active = withDomainItems(CustomerService::factory()->for($customer)->create(['start_date' => '2025-12-10', 'end_date' => '2026-12-10']), []);
    $endingSoon = withDomainItems(CustomerService::factory()->for($customer)->create(['start_date' => '2025-06-10', 'end_date' => '2026-06-20']), []);
    $cancelled = withDomainItems(CustomerService::factory()->for($customer)->cancelled()->create(), []);

    $name = Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('name');

    expect($name->record($active)->getDescriptionBelow())->toBeNull();

    $endingSoonHtml = $name->record($endingSoon)->getDescriptionBelow()->toHtml();
    expect($endingSoonHtml)->toContain('Yenilemesi yaklaşıyor')
        ->and($endingSoonHtml)->toContain('fi-mobile-badge')
        ->and($endingSoonHtml)->toContain('fi-color-warning');

    $cancelledHtml = $name->record($cancelled)->getDescriptionBelow()->toHtml();
    expect($cancelledHtml)->toContain('İptal edildi')
        ->and($cancelledHtml)->toContain('fi-mobile-badge');
});

it('hides only "Düzenle" below md and keeps "Ödeme al"; the description opens "Düzenle" with its "Borç işlemleri" section', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create();

    $manager = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class]);
    $table = $manager->instance()->getTable();

    expect($table->getAction('editCharge')->getExtraAttributes())->toBe(HIDDEN_BELOW_MD)
        ->and($table->getAction('recordPayment')->getExtraAttributes())->toBe([]);

    $cancelOnMobile = [
        TestAction::make('editChargeMobile')->table($charge),
        TestAction::make('cancelCharge')->schemaComponent(RecordActionsSection::DANGER_ACTIONS_KEY),
    ];

    $manager
        ->mountAction($cancelOnMobile)
        ->assertActionMounted($cancelOnMobile);
});

it('adds a status badge under "Kalan" at every width, only for an overdue, partially paid or due-soon charge, never for a plain open one', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $open = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-07-10']);
    $overdue = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-05']);

    $remaining = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('remaining');

    expect($remaining->record($open)->getDescriptionBelow())->toBeNull();

    $overdueHtml = $remaining->record($overdue)->getDescriptionBelow()->toHtml();
    expect($overdueHtml)->toContain('Gecikmiş (5 gün)')
        ->and($overdueHtml)->not->toContain('fi-mobile-badge')
        ->and($overdueHtml)->toContain('fi-color-danger');
});

it('keeps the partially paid "/ total" line alongside the status badge', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();
    $partial = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-07-10']);
    PaymentAllocation::factory()->create(['charge_id' => $partial->id, 'amount' => 100000]);

    $html = Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable()->getColumn('remaining')
        ->record($partial)->getDescriptionBelow()->toHtml();

    expect($html)->toContain('/ 4.000 TL')
        ->and($html)->toContain(__('charges.partially_paid'));
});

it('hides the recent payments row action below md; the amount opens the same delete confirmation', function () {
    $customer = Customer::factory()->create();
    $payment = Payment::factory()->for($customer)->create();

    $manager = Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class]);
    $table = $manager->instance()->getTable();

    expect($table->getAction('deletePayment')->getExtraAttributes())->toBe(HIDDEN_BELOW_MD);

    $manager
        ->mountAction(TestAction::make('deletePaymentMobile')->table($payment))
        ->callMountedAction()
        ->assertNotified();

    expect(Payment::query()->whereKey($payment->id)->exists())->toBeFalse();
});

// --- Kart düzeni: bölüm boşlukları, sağa yaslı Arşivle/Sil satırı ---------

it('renders contacts, billing and notes as three separate cards on the card, in the right column', function () {
    $customer = Customer::factory()->create();

    $schema = Livewire::test(ViewCustomer::class, ['record' => $customer->id])
        ->assertOk()
        ->assertSeeLivewire(ContactsRelationManager::class)
        ->assertSeeInOrder(['İlgili kişiler', 'Fatura bilgileri', 'Notlar'])
        ->instance()->getSchema('content');

    $infoColumn = $schema->getComponent('info_column', withHidden: true);
    expect($infoColumn)->not->toBeNull();

    $items = $infoColumn->getChildSchema()->getComponents();
    expect($items)->toHaveCount(3)
        ->and($items[0])->toBeInstanceOf(\Filament\Schemas\Components\Livewire::class)
        ->and($items[1])->toBeInstanceOf(Section::class)
        ->and($items[2])->toBeInstanceOf(Section::class);
});

it('gives the danger actions row no extra classes, relying on the schema\'s own section gap', function () {
    $customer = Customer::factory()->create();
    $schema = Livewire::test(ViewCustomer::class, ['record' => $customer->id])->instance()->getSchema('content');

    $dangerActions = $schema->getComponent('danger_actions', withHidden: true);

    expect($dangerActions)->not->toBeNull()
        ->and($dangerActions->getExtraAttributes())->toBe([]);
});

// --- T-73: iç içe <button> (T-71/T-72'nin gerçek kök nedeni) ---------------

/**
 * A `<button>` opening while another is still open is invalid HTML5; browsers close the
 * outer one (and every ancestor up to it) early, silently restructuring the DOM — exactly
 * what caused T-71/T-72's misplaced right column. `masterminds/html5` is not used here: on
 * this exact malformed shape it does not reproduce the browser's own recovery, so a small
 * stack-based scan of `<button` / `</button` tags is used instead.
 */
function assertNoNestedButtons(string $html, string $context): void
{
    preg_match_all('/<\/?button\b[^>]*>/i', $html, $matches);

    $depth = 0;
    $maxDepth = 0;

    foreach ($matches[0] as $tag) {
        if (str_starts_with($tag, '</')) {
            $depth = max(0, $depth - 1);

            continue;
        }

        $depth++;
        $maxDepth = max($maxDepth, $depth);
    }

    expect($maxDepth)->toBeLessThanOrEqual(1, "iç içe <button> bulundu: {$context} (en derin: {$maxDepth})");
}

it('never nests a <button> inside another across the card\'s five tables, with 2+ rows and with 0/1', function () {
    $busy = Customer::factory()->create();
    Contact::factory()->for($busy)->primary()->create();
    Contact::factory()->for($busy)->create();
    CustomerService::factory()->for($busy)->count(2)->create();
    CustomerService::factory()->for($busy)->cancelled()->count(2)->create();
    Charge::factory()->for($busy)->count(2)->create();
    Payment::factory()->for($busy)->count(2)->create();

    $quiet = Customer::factory()->create();
    Contact::factory()->for($quiet)->primary()->create();

    $empty = Customer::factory()->create();

    foreach ([$busy, $quiet, $empty] as $customer) {
        $html = Livewire::test(ViewCustomer::class, ['record' => $customer->id])->html();
        assertNoNestedButtons($html, "customer {$customer->id} full card");
    }

    assertNoNestedButtons(
        Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $busy, 'pageClass' => ViewCustomer::class])->html(),
        'Kişiler, 2 kayıt',
    );
    assertNoNestedButtons(
        Livewire::test(CustomerServicesRelationManager::class, ['ownerRecord' => $busy, 'pageClass' => ViewCustomer::class])->html(),
        'Hizmetler, 2 kayıt',
    );
    assertNoNestedButtons(
        Livewire::test(CancelledCustomerServicesRelationManager::class, ['ownerRecord' => $busy, 'pageClass' => ViewCustomer::class])->html(),
        'İptal edilenler, 2 kayıt',
    );
    assertNoNestedButtons(
        Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $busy, 'pageClass' => ViewCustomer::class])->html(),
        'Açık borçlar, 2 kayıt',
    );
    assertNoNestedButtons(
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $busy, 'pageClass' => ViewCustomer::class])->html(),
        'Son ödemeler, 2 kayıt',
    );
});

it('has no default record action on the contacts table, so its rows never wrap in a button', function () {
    $customer = Customer::factory()->create();
    $contact = Contact::factory()->for($customer)->primary()->create();

    $table = Livewire::test(ContactsRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
        ->instance()->getTable();

    expect($table->getRecordAction($contact))->toBeNull();
});

it('keeps the right column correctly nested for a customer with 2 contacts, matching the ROADMAP\'s own Logi example', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Contact::factory()->for($customer)->primary()->create();
    Contact::factory()->for($customer)->create();

    $html = Livewire::test(ViewCustomer::class, ['record' => $customer->id])->html();

    assertNoNestedButtons($html, 'Logi (2 kişi)');
    expect($html)->toContain('İlgili kişiler')
        ->and($html)->toContain('Fatura bilgileri')
        ->and($html)->toContain('Notlar');
});
