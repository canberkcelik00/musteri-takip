<?php

use App\Enums\Currency;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

function sampleCustomer(string $shortName): Customer
{
    return Customer::query()->where('short_name', $shortName)->firstOrFail();
}

function listedMoney(int $amount, Currency $currency = Currency::Try): array
{
    return [$currency->value => new Money($amount, $currency)];
}

it('shows the summary values of the sample customers in the list', function () {
    $this->seed();

    $logi = sampleCustomer('Logi');
    $anadolu = sampleCustomer('Anadolu Gıda');
    $deniz = sampleCustomer('Deniz Lojistik');
    $karadeniz = sampleCustomer('Karadeniz Doğa Derneği');

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('active_services_count', 1, $logi)
        ->assertTableColumnStateSet('open_debt', listedMoney(Money::parseAmount('3.000')), $logi)
        ->assertTableColumnFormattedStateSet('next_renewal_on', $logi->customerServices()->sole()->end_date->format('d.m.Y'), $logi)
        ->assertTableColumnStateSet('active_services_count', 1, $anadolu)
        ->assertTableColumnStateSet('open_debt', null, $anadolu)
        ->assertTableColumnFormattedStateSet('next_renewal_on', $anadolu->customerServices()->where('status', 'active')->sole()->end_date->format('d.m.Y'), $anadolu)
        ->assertTableColumnStateSet('active_services_count', 2, $deniz)
        ->assertTableColumnStateSet('open_debt', listedMoney(Money::parseAmount('6.000') + Money::parseAmount('450') + Money::parseAmount('1.500')), $deniz)
        ->assertTableColumnStateSet('active_services_count', 1, $karadeniz)
        ->assertTableColumnStateSet('open_debt', null, $karadeniz)
        ->assertTableColumnFormattedStateSet('next_renewal_on', $karadeniz->customerServices()->sole()->end_date->format('d.m.Y'), $karadeniz);
});

it('shows the open debt of each currency separately', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['amount' => 200000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 300000, 'currency' => Currency::Try]);
    Charge::factory()->for($customer)->create(['amount' => 5000, 'currency' => Currency::Usd]);

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('open_debt', [
            'TRY' => new Money(500000, Currency::Try),
            'USD' => new Money(5000, Currency::Usd),
        ], $customer)
        ->assertSeeInOrder(['5.000 TL', '50 USD']);
});

it('counts only the remaining amount of partially paid charges and ignores paid and cancelled ones', function () {
    $customer = Customer::factory()->create();
    $partial = Charge::factory()->for($customer)->create(['amount' => 400000]);
    PaymentAllocation::factory()->create(['charge_id' => $partial->id, 'amount' => 100000]);
    Charge::factory()->for($customer)->paid()->create(['amount' => 900000]);
    Charge::factory()->for($customer)->cancelled()->create(['amount' => 700000]);

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('open_debt', listedMoney(300000), $customer);
});

it('counts active services only and takes the nearest end date of them, free ones included', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create(['start_date' => '2026-03-01', 'end_date' => '2027-03-01']);
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2026-01-10', 'end_date' => '2027-01-10']);
    CustomerService::factory()->for($customer)->cancelled()->create(['start_date' => '2025-06-01', 'end_date' => '2026-06-01']);
    $withoutServices = Customer::factory()->create();

    Livewire::test(ListCustomers::class)
        ->assertTableColumnStateSet('active_services_count', 2, $customer)
        ->assertTableColumnFormattedStateSet('next_renewal_on', '10.01.2027', $customer)
        ->assertTableColumnStateSet('active_services_count', 0, $withoutServices)
        ->assertTableColumnFormattedStateSet('next_renewal_on', null, $withoutServices);
});

it('searches the list by short name', function () {
    $logi = Customer::factory()->create(['short_name' => 'Logi']);
    $yildiz = Customer::factory()->create(['short_name' => 'Yıldız Mobilya']);

    Livewire::test(ListCustomers::class)
        ->searchTable('Yıldız')
        ->assertCanSeeTableRecords([$yildiz])
        ->assertCanNotSeeTableRecords([$logi]);
});

it('creates a customer with its first contact as the primary contact', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'short_name' => 'Logi',
            'legal_title' => 'Logitech Bilgisayar Ürünleri Ltd. Şti.',
            'tax_office' => 'Kozyatağı',
            'tax_number' => '6080512345',
            'billing_address' => 'Kadıköy / İstanbul',
            'contact' => [
                'name' => 'Murat Kaya',
                'title' => 'Bilgi İşlem Sorumlusu',
                'phone' => '0532 412 18 90',
                'email' => 'murat.kaya@example.com',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::firstOrFail();

    expect($customer->short_name)->toBe('Logi')
        ->and($customer->tax_number)->toBe('6080512345')
        ->and($customer->contacts)->toHaveCount(1)
        ->and($customer->primaryContact->name)->toBe('Murat Kaya')
        ->and($customer->primaryContact->email)->toBe('murat.kaya@example.com');
});

it('requires a short name and a first contact name when creating', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm(['short_name' => null, 'contact' => ['name' => null]])
        ->call('create')
        ->assertHasFormErrors(['short_name' => 'required', 'contact.name' => 'required']);

    expect(Customer::count())->toBe(0)
        ->and(Contact::count())->toBe(0);
});

it('validates the e-mail address of the first contact', function () {
    Livewire::test(CreateCustomer::class)
        ->fillForm(['short_name' => 'Logi', 'contact' => ['name' => 'Murat Kaya', 'email' => 'geçersiz']])
        ->call('create')
        ->assertHasFormErrors(['contact.email' => 'email']);

    expect(Customer::count())->toBe(0);
});

it('rejects a short name that is already used, ignoring case and Turkish characters', function (string $duplicate) {
    Customer::factory()->create(['short_name' => 'Göksu']);

    Livewire::test(CreateCustomer::class)
        ->fillForm(['short_name' => $duplicate, 'contact' => ['name' => 'Murat Kaya']])
        ->call('create')
        ->assertHasFormErrors(['short_name' => 'unique']);

    expect(Customer::count())->toBe(1);
})->with(['same spelling' => 'Göksu', 'different case' => 'göksu', 'Turkish characters' => 'Goksu']);

it('edits a customer without the first contact section and keeps its own short name valid', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    Contact::factory()->for($customer)->primary()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->assertFormFieldHidden('contact.name')
        ->fillForm(['short_name' => 'Logi', 'notes' => 'Yeni not', 'tax_office' => 'Kadıköy'])
        ->call('save')
        ->assertHasNoFormErrors();

    $customer->refresh();

    expect($customer->notes)->toBe('Yeni not')
        ->and($customer->tax_office)->toBe('Kadıköy')
        ->and($customer->contacts()->count())->toBe(1);
});

it('hides archived customers from the list by default and shows them with the archive filter', function () {
    $active = Customer::factory()->create();
    $archived = Customer::factory()->archived()->create();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived])
        ->filterTable('archived', true)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active])
        ->filterTable('archived', null)
        ->assertCanSeeTableRecords([$active, $archived]);
});

it('archives and unarchives a customer without active services from the list', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->cancelled()->create();

    Livewire::test(ListCustomers::class)
        ->callAction(TestAction::make('archive')->table($customer))
        ->assertNotified(__('archive.archived'));

    expect($customer->refresh()->isArchived())->toBeTrue();

    Livewire::test(ListCustomers::class)
        ->filterTable('archived', true)
        ->callAction(TestAction::make('unarchive')->table($customer));

    expect($customer->refresh()->isArchived())->toBeFalse();
});

it('does not archive a customer with an active service and says why', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create();

    Livewire::test(ListCustomers::class)
        ->callAction(TestAction::make('archive')->table($customer))
        ->assertNotified('Önce müşterinin hizmetlerini iptal edin.');

    expect($customer->refresh()->isArchived())->toBeFalse();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->callAction('archive')
        ->assertNotified('Önce müşterinin hizmetlerini iptal edin.');

    expect($customer->refresh()->isArchived())->toBeFalse();
});

it('archives and unarchives a customer from its edit page', function () {
    $customer = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->assertActionVisible('archive')
        ->assertActionHidden('unarchive')
        ->callAction('archive');

    expect($customer->refresh()->isArchived())->toBeTrue();

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->assertActionHidden('archive')
        ->callAction('unarchive');

    expect($customer->refresh()->isArchived())->toBeFalse();
});

it('offers delete next to archive for a customer with nothing recorded and deletes it', function () {
    $customer = Customer::factory()->create();
    Contact::factory()->for($customer)->primary()->create();

    Livewire::test(ListCustomers::class)
        ->assertActionVisible(TestAction::make('delete')->table($customer))
        ->assertActionVisible(TestAction::make('archive')->table($customer))
        ->callAction(TestAction::make('delete')->table($customer));

    expect(Customer::query()->find($customer->id))->toBeNull()
        ->and(Contact::query()->count())->toBe(0);
});

it('offers only archive for a customer with a service, charge or payment', function () {
    $withService = CustomerService::factory()->cancelled()->create()->customer;
    $withCharge = Charge::factory()->create()->customer;
    $withPayment = Payment::factory()->create()->customer;

    $list = Livewire::test(ListCustomers::class);

    foreach ([$withService, $withCharge, $withPayment] as $customer) {
        $list->assertActionHidden(TestAction::make('delete')->table($customer))
            ->assertActionVisible(TestAction::make('archive')->table($customer));

        Livewire::test(EditCustomer::class, ['record' => $customer->id])
            ->assertActionHidden('delete')
            ->assertActionVisible('archive');

        Livewire::test(ViewCustomer::class, ['record' => $customer->id])
            ->assertActionHidden('delete');
    }
});

it('deletes a customer with nothing recorded from the edit page and the card and returns to the list', function () {
    $fromEdit = Customer::factory()->create();
    $fromCard = Customer::factory()->create();

    Livewire::test(EditCustomer::class, ['record' => $fromEdit->id])
        ->callAction('delete')
        ->assertRedirect(CustomerResource::getUrl('index'));

    Livewire::test(ViewCustomer::class, ['record' => $fromCard->id])
        ->assertActionVisible('delete')
        ->callAction('delete')
        ->assertRedirect(CustomerResource::getUrl('index'));

    expect(Customer::query()->count())->toBe(0);
});

it('offers delete for an archived customer that has nothing recorded', function () {
    $customer = Customer::factory()->create();
    $customer->archive();

    Livewire::test(ListCustomers::class)
        ->filterTable('archived', true)
        ->assertActionVisible(TestAction::make('delete')->table($customer))
        ->assertActionVisible(TestAction::make('unarchive')->table($customer));
});
