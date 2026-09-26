<?php

use App\Actions\CustomerServices\AssignCustomerService;
use App\Actions\Payments\RecordPayment;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\Customers\RelationManagers\CustomerServicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function customerCard(Customer $customer)
{
    return Livewire::test(ViewCustomer::class, ['record' => $customer->id]);
}

/**
 * @param  class-string  $manager
 */
function cardManager(string $manager, Customer $customer)
{
    return Livewire::test($manager, [
        'ownerRecord' => $customer,
        'pageClass' => ViewCustomer::class,
    ]);
}

/**
 * The Logi scenario of the PRD up to the renewal: a 4.000 TL package assigned on 01.03.2026,
 * 5.000 TL paid, the renewal charge created on 30.01.2027 with the 1.000 TL credit applied to it.
 */
function logiCustomer(): Customer
{
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Contact::factory()->for($customer)->primary()->create(['name' => 'Murat Kaya']);

    $package = Package::factory()->create(['name' => 'Kurumsal Paket', 'price' => Money::parseAmount('4.000'), 'currency' => Currency::Try]);
    $package->catalogServices()->attach([
        CatalogService::factory()->ofType(ServiceType::Hosting)->create()->id,
        CatalogService::factory()->ofType(ServiceType::Domain)->create()->id,
        CatalogService::factory()->ofType(ServiceType::Maintenance)->create()->id,
    ]);

    test()->travelTo('2026-03-01 10:00:00');
    app(AssignCustomerService::class)->handle($customer, $package, ['start_date' => '2026-03-01']);

    app(RecordPayment::class)->handle($customer, [
        'amount' => Money::parseAmount('5.000'),
        'currency' => Currency::Try,
        'paid_on' => '2026-03-01',
        'payment_method_id' => PaymentMethod::query()->value('id'),
    ]);

    return $customer;
}

function runScheduler(string $now): void
{
    test()->travelTo($now);
    test()->artisan('app:process-customer-services')->assertSuccessful();
}

it('opens the card from the customer list', function () {
    $customer = Customer::factory()->create();

    Livewire::test(ListCustomers::class)
        ->assertTableActionExists('edit', null, $customer)
        ->assertSeeHtml(route('filament.panel.resources.customers.view', ['record' => $customer], false));

    customerCard($customer)
        ->assertOk()
        ->assertSeeLivewire(ContactsRelationManager::class)
        ->assertSeeLivewire(CustomerServicesRelationManager::class)
        ->assertSeeLivewire(ChargesRelationManager::class)
        ->assertSeeLivewire(PaymentsRelationManager::class);
});

it('shows the Logi situation on the card after the first payment', function () {
    $customer = logiCustomer();
    $service = $customer->customerServices()->sole();
    $charge = $customer->charges()->sole();

    customerCard($customer)
        ->assertSee('Logi')
        ->assertSee('Alacak bakiyesi')
        ->assertSee('1.000 TL');

    expect($customer->openChargeTotals())->toBe([])
        ->and($customer->creditBalances())->toEqual(['TRY' => new Money(Money::parseAmount('1.000'), Currency::Try)]);

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$service])
        ->assertTableColumnStateSet('name', 'Kurumsal Paket', $service)
        ->assertTableColumnFormattedStateSet('start_date', '01.03.2026', $service)
        ->assertTableColumnFormattedStateSet('end_date', '01.03.2027', $service)
        ->assertTableColumnFormattedStateSet('price', '4.000 TL', $service)
        ->assertTableColumnFormattedStateSet('status', 'Aktif', $service);

    cardManager(ChargesRelationManager::class, $customer)
        ->assertCanNotSeeTableRecords([$charge])
        ->removeTableFilter('status')
        ->assertCanSeeTableRecords([$charge])
        ->assertTableColumnStateSet('status', 'Ödendi', $charge);

    cardManager(PaymentsRelationManager::class, $customer)
        ->assertCanSeeTableRecords($customer->payments)
        ->assertTableColumnFormattedStateSet('amount', '5.000 TL', $customer->payments->sole())
        ->assertTableColumnFormattedStateSet('paid_on', '01.03.2026', $customer->payments->sole());
});

it('shows the Logi renewal as an overdue charge with the credit applied', function () {
    $customer = logiCustomer();

    runScheduler('2027-01-30 00:00:00');
    runScheduler('2027-03-01 00:00:00');
    $this->travelTo('2027-03-15 10:00:00');

    $renewal = $customer->charges()->where('period_start', '2027-03-01')->sole();
    $service = $customer->customerServices()->sole();

    customerCard($customer)
        ->assertSee('Açık borç')
        ->assertSee('3.000 TL')
        ->assertDontSee('1.000 TL');

    expect($customer->creditBalances())->toBe([]);

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertTableColumnFormattedStateSet('start_date', '01.03.2027', $service)
        ->assertTableColumnFormattedStateSet('end_date', '01.03.2028', $service)
        ->assertTableColumnFormattedStateSet('price', '4.000 TL', $service);

    cardManager(ChargesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$renewal])
        ->assertCanNotSeeTableRecords($customer->charges()->whereKeyNot($renewal->id)->get())
        ->assertTableColumnFormattedStateSet('amount', '4.000 TL', $renewal)
        ->assertTableColumnFormattedStateSet('remaining', '3.000 TL', $renewal)
        ->assertTableColumnFormattedStateSet('due_date', '01.03.2027', $renewal)
        ->assertTableColumnStateSet('status', 'Gecikmiş (14 gün)', $renewal);
});

it('shows a cancelled service as its own row after the active ones', function () {
    $customer = Customer::factory()->create();
    $cancelled = CustomerService::factory()->for($customer)->cancelled()->create(['end_date' => now()->addMonths(1)->toDateString(), 'start_date' => now()->subMonths(11)->toDateString()]);
    $active = CustomerService::factory()->for($customer)->create(['start_date' => now()->subMonths(2)->toDateString()]);
    $later = CustomerService::factory()->for($customer)->create(['start_date' => now()->subMonths(1)->toDateString()]);

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$active, $later, $cancelled], inOrder: true)
        ->assertTableColumnFormattedStateSet('status', 'İptal edildi', $cancelled)
        ->assertTableColumnFormattedStateSet('status', 'Aktif', $active);
});

it('shows a free service with "Ücretsiz" instead of its price', function () {
    $customer = Customer::factory()->create();
    $free = CustomerService::factory()->for($customer)->free()->create();

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertTableColumnFormattedStateSet('price', 'Ücretsiz', $free);
});

it('tells open, partially paid and overdue charges apart', function () {
    $this->travelTo('2026-06-10 12:00:00');
    $customer = Customer::factory()->create();

    $dueToday = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-06-10']);
    $partial = Charge::factory()->for($customer)->create(['amount' => 400000, 'due_date' => '2026-07-01']);
    PaymentAllocation::factory()->create(['charge_id' => $partial->id, 'amount' => 100000]);
    $overdue = Charge::factory()->for($customer)->create(['amount' => 200000, 'due_date' => '2026-06-05']);
    $partialOverdue = Charge::factory()->for($customer)->create(['amount' => 300000, 'due_date' => '2026-06-09']);
    PaymentAllocation::factory()->create(['charge_id' => $partialOverdue->id, 'amount' => 100000]);
    $paid = Charge::factory()->for($customer)->paid()->create();
    $cancelled = Charge::factory()->for($customer)->cancelled()->create();

    cardManager(ChargesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$overdue, $partialOverdue, $dueToday, $partial], inOrder: false)
        ->assertCanNotSeeTableRecords([$paid, $cancelled])
        ->assertTableColumnStateSet('status', 'Açık', $dueToday)
        ->assertTableColumnStateSet('status', 'Kısmi ödendi', $partial)
        ->assertTableColumnStateSet('status', 'Gecikmiş (5 gün)', $overdue)
        ->assertTableColumnStateSet('status', 'Gecikmiş (1 gün)', $partialOverdue)
        ->assertTableColumnFormattedStateSet('remaining', '3.000 TL', $partial)
        ->assertTableColumnFormattedStateSet('remaining', '2.000 TL', $partialOverdue)
        ->removeTableFilter('status')
        ->assertCanSeeTableRecords([$paid, $cancelled])
        ->assertTableColumnStateSet('status', 'Ödendi', $paid)
        ->assertTableColumnStateSet('status', 'İptal edildi', $cancelled)
        ->assertTableColumnStateSet('remaining', null, $paid);
});

it('filters the charges by status', function () {
    $customer = Customer::factory()->create();
    $open = Charge::factory()->for($customer)->create();
    $paid = Charge::factory()->for($customer)->paid()->create();

    cardManager(ChargesRelationManager::class, $customer)
        ->filterTable('status', ChargeStatus::Paid->value)
        ->assertCanSeeTableRecords([$paid])
        ->assertCanNotSeeTableRecords([$open]);
});

it('shows the credit balance and the open debt of each currency separately', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 200000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd]);
    Payment::factory()->for($customer)->create(['amount' => 60000, 'currency' => Currency::Try]);
    Payment::factory()->for($customer)->create(['amount' => 2500, 'currency' => Currency::Eur]);

    customerCard($customer)
        ->assertSeeInOrder(['Açık borç', '2.000 TL', '50 USD'])
        ->assertSeeInOrder(['Alacak bakiyesi', '600 TL', '25 EUR']);
});

it('shows a dash when there is no open debt or credit balance', function () {
    $customer = Customer::factory()->create();

    customerCard($customer)
        ->assertSeeInOrder(['Açık borç', '—', 'Alacak bakiyesi', '—']);
});

it('lists only the records of the customer on the card', function () {
    $customer = Customer::factory()->create();
    $stranger = Customer::factory()->create();

    $ownService = CustomerService::factory()->for($customer)->create();
    $otherService = CustomerService::factory()->for($stranger)->create();
    $ownCharge = Charge::factory()->for($customer)->create();
    $otherCharge = Charge::factory()->for($stranger)->create();
    $ownPayment = Payment::factory()->for($customer)->create();
    $otherPayment = Payment::factory()->for($stranger)->create();

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$ownService])
        ->assertCanNotSeeTableRecords([$otherService]);

    cardManager(ChargesRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$ownCharge])
        ->assertCanNotSeeTableRecords([$otherCharge]);

    cardManager(PaymentsRelationManager::class, $customer)
        ->assertCanSeeTableRecords([$ownPayment])
        ->assertCanNotSeeTableRecords([$otherPayment]);
});

it('keeps only the charges read-only on the card; services and payments have their actions', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->create();
    $charge = Charge::factory()->for($customer)->create();
    $payment = Payment::factory()->for($customer)->create();

    $charges = cardManager(ChargesRelationManager::class, $customer)->assertCanSeeTableRecords([$charge]);
    $chargesTable = $charges->instance()->getTable();

    expect($chargesTable->getHeaderActions())->toBeEmpty()
        ->and($chargesTable->getRecordActions())->toBeEmpty();

    cardManager(CustomerServicesRelationManager::class, $customer)
        ->assertActionExists(TestAction::make('assign')->table())
        ->assertActionExists(TestAction::make('editService')->table($service));

    cardManager(PaymentsRelationManager::class, $customer)
        ->assertActionExists(TestAction::make('recordPayment')->table())
        ->assertActionExists(TestAction::make('deletePayment')->table($payment))
        ->assertTableActionDoesNotExist('edit', null, $payment);
});
