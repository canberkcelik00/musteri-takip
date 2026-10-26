<?php

use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Livewire\Windows\AssignService;
use App\Livewire\Windows\CancelCharge;
use App\Livewire\Windows\CancelService;
use App\Livewire\Windows\Confirm;
use App\Livewire\Windows\Contact as ContactWindow;
use App\Livewire\Windows\EditCharge;
use App\Livewire\Windows\EditCustomer;
use App\Livewire\Windows\EditService;
use App\Livewire\Windows\MarkFree;
use App\Livewire\Windows\ReactivateService;
use App\Livewire\Windows\RemoveFree;
use App\Livewire\Windows\SendReminder;
use App\Livewire\Windows\SplitCharge;
use App\Livewire\Windows\ViewPayment;
use App\Livewire\Windows\ViewUsage;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->travelTo('2026-06-15 10:00:00');
});

/**
 * An active 4.000 TL service for 01.03.2026 – 01.03.2027; today is 15.06.2026, so the charge of the period starting
 * 01.03.2027 is the upcoming one.
 */
function uiWindowService(array $attributes = []): CustomerService
{
    return CustomerService::factory()->create([
        'name' => 'Kurumsal Paket',
        'price' => 400000,
        'currency' => Currency::Try,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
        ...$attributes,
    ]);
}

function uiChargeOfPeriod(CustomerService $service, string $periodStart, array $attributes = []): Charge
{
    return Charge::factory()->forCustomerService($service)->create([
        'due_date' => $periodStart,
        'period_start' => $periodStart,
        'period_end' => CarbonImmutable::parse($periodStart)->addYear()->toDateString(),
        ...$attributes,
    ]);
}

// --- Müşteri ------------------------------------------------------------

describe('edit customer window', function () {
    it('fills the fields and saves the changes', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi', 'legal_title' => 'Eski Ünvan']);

        Livewire::test(EditCustomer::class, ['customer' => $customer->id])
            ->assertSet('short_name', 'Logi')
            ->assertSet('legal_title', 'Eski Ünvan')
            ->set('legal_title', 'Yeni Ünvan')
            ->set('tax_office', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customers-changed')
            ->assertDispatched('close-window')
            ->assertDispatched('notify', message: __('customers.updated'));

        expect($customer->fresh()->legal_title)->toBe('Yeni Ünvan')->and($customer->fresh()->tax_office)->toBeNull();
    });

    it('lets the customer keep its own short name but not take another one', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        Customer::factory()->create(['short_name' => 'Başka']);

        Livewire::test(EditCustomer::class, ['customer' => $customer->id])->call('save')->assertHasNoErrors();

        Livewire::test(EditCustomer::class, ['customer' => $customer->id])
            ->set('short_name', 'Başka')
            ->call('save')
            ->assertHasErrors(['short_name' => 'unique']);
    });

    it('does not let the browser pick another customer', function () {
        $customer = Customer::factory()->create();

        Livewire::test(EditCustomer::class, ['customer' => $customer->id])->set('customerId', 99);
    })->throws('Cannot update locked property');
});

describe('contact window', function () {
    it('adds a contact, optionally as the primary one', function () {
        $customer = Customer::factory()->create();
        $first = Contact::factory()->for($customer)->primary()->create(['name' => 'Ayşe']);

        Livewire::test(ContactWindow::class, ['customer' => $customer->id])
            ->set('name', 'Zeynep')
            ->set('email', 'z@example.com')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('contacts-changed')
            ->assertDispatched('close-window');

        expect($first->fresh()->is_primary)->toBeTrue()
            ->and($customer->contacts()->where('name', 'Zeynep')->sole()->is_primary)->toBeFalse();

        Livewire::test(ContactWindow::class, ['customer' => $customer->id])
            ->set('name', 'Mehmet')
            ->set('make_primary', true)
            ->call('save');

        expect($customer->contacts()->where('is_primary', true)->count())->toBe(1)
            ->and($customer->contacts()->where('name', 'Mehmet')->sole()->is_primary)->toBeTrue();
    });

    it('requires a name and a valid e-mail address', function () {
        $customer = Customer::factory()->create();

        Livewire::test(ContactWindow::class, ['customer' => $customer->id])
            ->set('email', 'nope')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'email']);
    });

    it('edits a contact of the customer and refuses one of another customer', function () {
        $customer = Customer::factory()->create();
        $contact = Contact::factory()->for($customer)->create(['name' => 'Ayşe', 'phone' => '111']);

        Livewire::test(ContactWindow::class, ['customer' => $customer->id, 'contact' => $contact->id])
            ->assertSet('name', 'Ayşe')
            ->set('phone', '222')
            ->call('save');

        expect($contact->fresh()->phone)->toBe('222');

        $other = Customer::factory()->create();

        Livewire::test(ContactWindow::class, ['customer' => $other->id, 'contact' => $contact->id])
            ->assertSet('contactId', null)
            ->assertSet('name', '');
    });

    it('makes another contact the primary one from its section, and offers neither action for the primary one', function () {
        $customer = Customer::factory()->create();
        $primary = Contact::factory()->for($customer)->primary()->create();
        $other = Contact::factory()->for($customer)->create();

        $primaryWindow = Livewire::test(ContactWindow::class, ['customer' => $customer->id, 'contact' => $primary->id]);
        expect($primaryWindow->html())->not->toContain(__('contacts.make_primary'))->not->toContain("action: 'delete-contact'");

        $window = Livewire::test(ContactWindow::class, ['customer' => $customer->id, 'contact' => $other->id]);
        expect($window->html())->toContain("action: 'delete-contact', id: {$other->id}");

        $window->call('makePrimary')->assertDispatched('contacts-changed')->assertDispatched('close-window');

        expect($other->fresh()->is_primary)->toBeTrue()->and($primary->fresh()->is_primary)->toBeFalse();
    });
});

// --- Onay penceresi ---------------------------------------------------------

describe('confirm window', function () {
    it('archives a customer without active services', function () {
        $customer = Customer::factory()->create();

        Livewire::test(Confirm::class, ['action' => 'archive-customer', 'id' => $customer->id])
            ->assertSee(__('archive.archive_heading'))
            ->call('confirm')
            ->assertDispatched('customers-changed')
            ->assertDispatched('notify', message: __('archive.archived'));

        expect($customer->fresh()->isArchived())->toBeTrue();
    });

    it('keeps a customer with an active service and says why in the window', function () {
        $service = uiWindowService();

        Livewire::test(Confirm::class, ['action' => 'archive-customer', 'id' => $service->customer_id])
            ->call('confirm')
            ->assertHasErrors('form')
            ->assertNotDispatched('close-window');

        expect($service->customer->fresh()->isArchived())->toBeFalse();
    });

    it('takes a customer out of the archive', function () {
        $customer = Customer::factory()->archived()->create();

        Livewire::test(Confirm::class, ['action' => 'unarchive-customer', 'id' => $customer->id])->call('confirm');

        expect($customer->fresh()->isArchived())->toBeFalse();
    });

    it('deletes a customer nothing depends on and goes back to the list, and refuses one in use', function () {
        $customer = Customer::factory()->create();

        Livewire::test(Confirm::class, ['action' => 'delete-customer', 'id' => $customer->id])
            ->call('confirm')
            ->assertRedirect(route('app.customers.index'));

        expect(Customer::query()->find($customer->id))->toBeNull();

        $used = Charge::factory()->create()->customer;

        Livewire::test(Confirm::class, ['action' => 'delete-customer', 'id' => $used->id])
            ->call('confirm')
            ->assertHasErrors('form');

        expect(Customer::query()->find($used->id))->not->toBeNull();
    });

    it('deletes a wrongly entered payment and reopens the charges it paid', function () {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'due_date' => '2026-07-01']);
        $payment = Payment::factory()->for($customer)->create(['amount' => 100000]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 100000]);
        $charge->update(['status' => ChargeStatus::Paid]);

        Livewire::test(Confirm::class, ['action' => 'delete-payment', 'id' => $payment->id])
            ->assertSee(__('payments.delete_description'))
            ->call('confirm')
            ->assertDispatched('payments-changed')
            ->assertDispatched('notify', message: __('payments.deleted_reopened', ['count' => 1]));

        expect(Payment::query()->find($payment->id))->toBeNull()
            ->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('deletes a non-primary contact and refuses the primary one', function () {
        $customer = Customer::factory()->create();
        $primary = Contact::factory()->for($customer)->primary()->create();
        $other = Contact::factory()->for($customer)->create();

        Livewire::test(Confirm::class, ['action' => 'delete-contact', 'id' => $primary->id])->call('confirm')->assertHasErrors('form');
        Livewire::test(Confirm::class, ['action' => 'delete-contact', 'id' => $other->id])->call('confirm')->assertDispatched('contacts-changed');

        expect(Contact::query()->find($other->id))->toBeNull()->and(Contact::query()->find($primary->id))->not->toBeNull();
    });

    it('deletes a service with no charge and refuses one with a charge', function () {
        $free = uiWindowService();
        $withCharge = uiWindowService(['name' => 'Borçlu']);
        uiChargeOfPeriod($withCharge, '2027-03-01');

        Livewire::test(Confirm::class, ['action' => 'delete-service', 'id' => $free->id])->call('confirm')->assertDispatched('customer-services-changed');
        Livewire::test(Confirm::class, ['action' => 'delete-service', 'id' => $withCharge->id])->call('confirm')->assertHasErrors('form');

        expect(CustomerService::query()->find($free->id))->toBeNull()->and(CustomerService::query()->find($withCharge->id))->not->toBeNull();
    });

    it('runs nothing the list does not name and cannot be pointed elsewhere by the browser', function () {
        expect(fn () => Livewire::test(Confirm::class, ['action' => 'drop-everything', 'id' => 1]))->toThrow(Exception::class);

        $customer = Customer::factory()->create();

        expect(fn () => Livewire::test(Confirm::class, ['action' => 'archive-customer', 'id' => $customer->id])->set('action', 'delete-customer'))
            ->toThrow(Exception::class, 'Cannot update locked property');
    });
});

// --- Hizmet -----------------------------------------------------------------

describe('assign service window', function () {
    it('prefills the price and currency from the catalog service and assigns it with its first charge', function () {
        $customer = Customer::factory()->create();
        $catalog = CatalogService::factory()->create(['name' => 'Hosting', 'type' => ServiceType::Hosting, 'price' => 120000, 'currency' => Currency::Try]);

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->assertSet('start_date', '2026-06-15')
            ->set('sold', "catalog_service:{$catalog->id}")
            ->assertSet('price', '1.200')
            ->assertSet('currency', 'TRY')
            ->set("items.{$catalog->id}.details.domain", 'logi.com.tr')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-services-changed')
            ->assertDispatched('charges-changed')
            ->assertDispatched('close-window')
            ->assertDispatched('notify', message: __('customer_services.assigned'));

        $service = $customer->customerServices()->sole();

        expect($service->name)->toBe('Hosting')
            ->and($service->price)->toBe(120000)
            ->and($service->domains())->toBe(['logi.com.tr'])
            ->and($customer->charges()->count())->toBe(1);
    });

    it('assigns a package with one item per catalog service', function () {
        $customer = Customer::factory()->create();
        $package = Package::factory()->create(['name' => 'Kurumsal', 'price' => 250000, 'currency' => Currency::Try]);
        $package->catalogServices()->attach(CatalogService::factory()->count(2)->create());

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->set('sold', "package:{$package->id}")
            ->assertSet('price', '2.500')
            ->call('save')
            ->assertHasNoErrors();

        expect($customer->customerServices()->sole()->items)->toHaveCount(2);
    });

    it('does not create the first charge when asked not to, and none for a free service', function () {
        $customer = Customer::factory()->create();
        $catalog = CatalogService::factory()->create(['price' => 50000]);

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->set('sold', "catalog_service:{$catalog->id}")
            ->set('create_first_charge', '0')
            ->call('save');

        expect($customer->charges()->count())->toBe(0);

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->set('sold', "catalog_service:{$catalog->id}")
            ->set('is_free', true)
            ->call('save');

        expect($customer->customerServices()->count())->toBe(2)->and($customer->charges()->count())->toBe(0);
    });

    it('requires the sale, a price and a start date, and refuses a price of zero under the price field', function () {
        $customer = Customer::factory()->create();
        $catalog = CatalogService::factory()->create();

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->set('start_date', '')
            ->call('save')
            ->assertHasErrors(['sold', 'price', 'start_date']);

        Livewire::test(AssignService::class, ['customer' => $customer->id])
            ->set('sold', "catalog_service:{$catalog->id}")
            ->set('price', '0')
            ->call('save')
            ->assertHasErrors(['price' => __('customer_services.price_not_positive')]);

        expect($customer->customerServices()->count())->toBe(0);
    });

    it('lists archived catalog services last and labelled', function () {
        $customer = Customer::factory()->create();
        CatalogService::factory()->create(['name' => 'Aktif Hizmet']);
        CatalogService::factory()->archived()->create(['name' => 'Eski Hizmet']);

        $html = Livewire::test(AssignService::class, ['customer' => $customer->id])->html();

        expect($html)->toContain('Aktif Hizmet')->toContain('Eski Hizmet (arşivlendi)')->toContain(__('customer_services.group_services'));
    });
});

describe('edit service window', function () {
    it('fills the fields and saves price, dates and notes', function () {
        $service = uiWindowService();

        Livewire::test(EditService::class, ['service' => $service->id])
            ->assertSet('price', '4.000')
            ->assertSet('start_date', '2026-03-01')
            ->assertSet('end_date', '2027-03-01')
            ->set('price', '4.500')
            ->set('notes', 'Not')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-services-changed')
            ->assertDispatched('notify', message: __('customer_services.updated'));

        expect($service->fresh()->price)->toBe(450000)->and($service->fresh()->notes)->toBe('Not');
    });

    it('shows a rule refusal under the field it is about', function () {
        $service = uiWindowService();

        Livewire::test(EditService::class, ['service' => $service->id])
            ->set('end_date', '2026-01-01')
            ->call('save')
            ->assertHasErrors(['end_date' => __('customer_services.end_date_not_after_start_date')]);

        Livewire::test(EditService::class, ['service' => $service->id])
            ->set('price', '0')
            ->call('save')
            ->assertHasErrors(['price' => __('customer_services.price_not_positive')]);
    });

    it('edits the details of the items and keeps the values of the other fields', function () {
        $service = CustomerService::factory()->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))->create();
        $item = $service->items()->firstOrFail();
        $item->update(['details' => ['domain' => 'a.com', 'server' => 'srv14.natro.com']]);

        Livewire::test(EditService::class, ['service' => $service->id])
            ->assertSet("items.{$item->id}.details.domain", 'a.com')
            ->set("items.{$item->id}.details.domain", 'b.com')
            ->call('save')
            ->assertHasNoErrors();

        expect($item->fresh()->details)->toMatchArray(['domain' => 'b.com', 'server' => 'srv14.natro.com']);
    });

    it('locks price, currency and dates of a cancelled service but saves its notes', function () {
        $service = uiWindowService(['status' => CustomerServiceStatus::Cancelled]);

        Livewire::test(EditService::class, ['service' => $service->id])
            ->assertSee(__('customer_services.cancelled_locked_helper'))
            ->set('price', '9.999')
            ->set('notes', 'Sadece not')
            ->call('save')
            ->assertHasNoErrors();

        expect($service->fresh()->price)->toBe(400000)->and($service->fresh()->notes)->toBe('Sadece not');
    });

    it('offers the right actions for an active, a free and a cancelled service', function () {
        $active = Livewire::test(EditService::class, ['service' => uiWindowService()->id])->html();
        $free = Livewire::test(EditService::class, ['service' => uiWindowService(['is_free' => true])->id])->html();
        $cancelled = Livewire::test(EditService::class, ['service' => uiWindowService(['status' => CustomerServiceStatus::Cancelled])->id])->html();

        expect($active)->toContain("name: 'mark-free'")->toContain("name: 'cancel-service'")->not->toContain("name: 'remove-free'")->not->toContain("name: 'reactivate-service'");
        expect($free)->toContain("name: 'remove-free'")->not->toContain("name: 'mark-free'");
        expect($cancelled)->toContain("name: 'reactivate-service'")->not->toContain("name: 'cancel-service'");
    });

    it('offers delete only while nothing depends on the service', function () {
        $service = uiWindowService();

        expect(Livewire::test(EditService::class, ['service' => $service->id])->html())->toContain("action: 'delete-service'");

        uiChargeOfPeriod($service, '2027-03-01');

        expect(Livewire::test(EditService::class, ['service' => $service->id])->html())->not->toContain("action: 'delete-service'");
    });
});

describe('cancel service window', function () {
    it('offers the upcoming period charge checked and cancels it with the date and note', function () {
        $service = uiWindowService();
        $upcoming = uiChargeOfPeriod($service, '2027-03-01');

        Livewire::test(CancelService::class, ['service' => $service->id])
            ->assertSet('cancelled_on', '2026-06-15')
            ->assertSet('cancel_upcoming_charges', true)
            ->assertSee(__('customer_services.cancel_upcoming_charges', ['amount' => '4.000 TL']))
            ->set('cancelled_on', '2026-06-10')
            ->set('cancel_note', 'Siteyi kapattı.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-services-changed')
            ->assertDispatched('notify', message: __('customer_services.cancelled'));

        $service->refresh();

        expect($service->status)->toBe(CustomerServiceStatus::Cancelled)
            ->and($service->cancelled_on->toDateString())->toBe('2026-06-10')
            ->and($service->cancel_note)->toBe('Siteyi kapattı.')
            ->and($upcoming->fresh()->status)->toBe(ChargeStatus::Cancelled);
    });

    it('keeps the upcoming period charge when the box is unchecked', function () {
        $service = uiWindowService();
        $upcoming = uiChargeOfPeriod($service, '2027-03-01');

        Livewire::test(CancelService::class, ['service' => $service->id])->set('cancel_upcoming_charges', false)->call('save');

        expect($service->fresh()->status)->toBe(CustomerServiceStatus::Cancelled)->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('shows the overdue and other open charges as staying and never cancels them', function () {
        $service = uiWindowService();
        $overdue = uiChargeOfPeriod($service, '2026-03-01', ['amount' => 150000]);
        uiChargeOfPeriod($service, '2027-03-01');

        Livewire::test(CancelService::class, ['service' => $service->id])
            ->assertSee(__('customer_services.stays_overdue', ['amount' => '1.500 TL']))
            ->call('save');

        expect($overdue->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('requires the date', function () {
        $service = uiWindowService();

        Livewire::test(CancelService::class, ['service' => $service->id])->set('cancelled_on', '')->call('save')->assertHasErrors('cancelled_on');
    });
});

describe('mark free window', function () {
    it('marks the service free and cancels the upcoming charge when the box is checked', function () {
        $service = uiWindowService();
        $upcoming = uiChargeOfPeriod($service, '2027-03-01');

        Livewire::test(MarkFree::class, ['service' => $service->id])
            ->assertSee(__('customer_services.cancel_upcoming_charges', ['amount' => '4.000 TL']))
            ->call('save')
            ->assertDispatched('notify', message: __('customer_services.marked_free'));

        expect($service->fresh()->is_free)->toBeTrue()->and($upcoming->fresh()->status)->toBe(ChargeStatus::Cancelled);
    });

    it('leaves the upcoming charge when unchecked', function () {
        $service = uiWindowService();
        $upcoming = uiChargeOfPeriod($service, '2027-03-01');

        Livewire::test(MarkFree::class, ['service' => $service->id])->set('cancel_upcoming_charges', false)->call('save');

        expect($service->fresh()->is_free)->toBeTrue()->and($upcoming->fresh()->status)->toBe(ChargeStatus::Open);
    });
});

describe('remove free window', function () {
    it('makes the service paid again with the new price', function () {
        $service = uiWindowService(['is_free' => true, 'price' => 100000]);

        Livewire::test(RemoveFree::class, ['service' => $service->id])
            ->assertSet('price', '1.000')
            ->set('price', '1.500')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('customer-services-changed');

        expect($service->fresh()->is_free)->toBeFalse()->and($service->fresh()->price)->toBe(150000);
    });

    it('refuses a price of zero under the price field', function () {
        $service = uiWindowService(['is_free' => true]);

        Livewire::test(RemoveFree::class, ['service' => $service->id])->set('price', '0')->call('save')->assertHasErrors('price');

        expect($service->fresh()->is_free)->toBeTrue();
    });

    it('warns instead of working when the service is no longer free', function () {
        $service = uiWindowService(['is_free' => false]);

        Livewire::test(RemoveFree::class, ['service' => $service->id])
            ->call('save')
            ->assertDispatched('notify', message: __('customer_services.free_action_not_applied'), tone: 'danger');
    });
});

describe('reactivate service window', function () {
    it('reactivates a service whose period has not ended and reopens its cancelled charge', function () {
        $service = uiWindowService(['status' => CustomerServiceStatus::Cancelled]);
        $charge = uiChargeOfPeriod($service, '2027-03-01', ['status' => ChargeStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'x']);

        Livewire::test(ReactivateService::class, ['service' => $service->id])
            ->assertDontSee(__('customer_services.reactivate_new_start_date'))
            ->assertSee(__('customer_services.reopen_charge', ['amount' => '4.000 TL']))
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: __('customer_services.reactivated'));

        expect($service->fresh()->status)->toBe(CustomerServiceStatus::Active)->and($charge->fresh()->status)->toBe(ChargeStatus::Open);
    });

    it('asks for a new start date once the period has ended', function () {
        $service = uiWindowService(['status' => CustomerServiceStatus::Cancelled, 'start_date' => '2025-01-01', 'end_date' => '2026-01-01']);

        Livewire::test(ReactivateService::class, ['service' => $service->id])
            ->assertSee(__('customer_services.reactivate_new_start_date'))
            ->assertSee(__('customer_services.create_first_charge'))
            ->set('start_date', '2026-06-01')
            ->call('save')
            ->assertHasNoErrors();

        $service->refresh();

        expect($service->status)->toBe(CustomerServiceStatus::Active)->and($service->start_date->toDateString())->toBe('2026-06-01');
    });

    it('does not create the first charge when asked not to', function () {
        $service = uiWindowService(['status' => CustomerServiceStatus::Cancelled, 'start_date' => '2025-01-01', 'end_date' => '2026-01-01']);

        Livewire::test(ReactivateService::class, ['service' => $service->id])
            ->set('start_date', '2026-06-01')
            ->set('create_first_charge', '0')
            ->call('save');

        expect($service->charges()->count())->toBe(0);
    });

    it('says to unarchive an archived customer first', function () {
        $service = uiWindowService(['status' => CustomerServiceStatus::Cancelled]);
        $service->customer->archive();

        Livewire::test(ReactivateService::class, ['service' => $service->id])
            ->assertSee(__('customer_services.customer_archived'))
            ->call('save')
            ->assertHasErrors('form');
    });
});

// --- Borç -------------------------------------------------------------------

describe('edit charge window', function () {
    it('changes the amount and due date of an open charge', function () {
        $charge = Charge::factory()->create(['amount' => 100000, 'due_date' => '2026-07-01']);

        Livewire::test(EditCharge::class, ['charge' => $charge->id])
            ->assertSet('amount', '1.000')
            ->assertSet('due_date', '2026-07-01')
            ->set('amount', '1.250')
            ->set('due_date', '2026-08-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('charges-changed')
            ->assertDispatched('notify', message: __('charges.updated'));

        expect($charge->fresh()->amount)->toBe(125000)->and($charge->fresh()->due_date->toDateString())->toBe('2026-08-01');
    });

    it('locks the amount once a payment is allocated and changes only the due date', function () {
        $charge = Charge::factory()->create(['amount' => 100000, 'due_date' => '2026-07-01']);
        $payment = Payment::factory()->for($charge->customer)->create(['amount' => 30000]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 30000]);

        $window = Livewire::test(EditCharge::class, ['charge' => $charge->id])
            ->assertSee(__('charges.has_allocations'))
            ->set('amount', '5')
            ->set('due_date', '2026-09-01')
            ->call('save')
            ->assertHasNoErrors();

        expect($charge->fresh()->amount)->toBe(100000)->and($charge->fresh()->due_date->toDateString())->toBe('2026-09-01');
        expect($window->html())->not->toContain("name: 'split-charge'")->not->toContain("name: 'cancel-charge'");
    });

    it('refuses an amount of zero under the amount field', function () {
        $charge = Charge::factory()->create();

        Livewire::test(EditCharge::class, ['charge' => $charge->id])
            ->set('amount', '0')
            ->call('save')
            ->assertHasErrors(['amount' => __('charges.amount_not_positive')]);
    });

    it('offers split and cancel for a charge without payments', function () {
        $charge = Charge::factory()->create();

        expect(Livewire::test(EditCharge::class, ['charge' => $charge->id])->html())
            ->toContain("name: 'split-charge', params: { charge: {$charge->id} }")
            ->toContain("name: 'cancel-charge', params: { charge: {$charge->id} }");
    });

    it('offers the reminder only for a charge that is eligible', function () {
        $plain = Charge::factory()->create();

        expect(Livewire::test(EditCharge::class, ['charge' => $plain->id])->html())->not->toContain("name: 'send-reminder'");
    });
});

describe('split charge window', function () {
    it('splits an amount off into a new charge', function () {
        $charge = Charge::factory()->create(['amount' => 100000, 'description' => 'Hosting', 'due_date' => '2026-07-01']);

        Livewire::test(SplitCharge::class, ['charge' => $charge->id])
            ->assertSet('due_date', '2026-07-01')
            ->set('amount', '300')
            ->set('due_date', '2026-08-01')
            ->set('description', 'İkinci taksit')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('charges-changed')
            ->assertDispatched('notify', message: __('charges.split_done'));

        expect($charge->fresh()->amount)->toBe(70000)
            ->and(Charge::query()->where('description', 'İkinci taksit')->sole()->amount)->toBe(30000);
    });

    it('refuses an amount that is not between zero and the charge', function () {
        $charge = Charge::factory()->create(['amount' => 100000]);

        Livewire::test(SplitCharge::class, ['charge' => $charge->id])
            ->set('amount', '1.000')
            ->call('save')
            ->assertHasErrors(['amount' => __('charges.invalid_split_amount')]);
    });
});

describe('cancel charge window', function () {
    it('cancels with a reason', function () {
        $charge = Charge::factory()->create();

        Livewire::test(CancelCharge::class, ['charge' => $charge->id])
            ->set('reason', 'Yanlış girildi')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('charges-changed')
            ->assertDispatched('notify', message: __('charges.cancelled'));

        expect($charge->fresh()->status)->toBe(ChargeStatus::Cancelled)->and($charge->fresh()->cancel_reason)->toBe('Yanlış girildi');
    });

    it('requires the reason', function () {
        $charge = Charge::factory()->create();

        Livewire::test(CancelCharge::class, ['charge' => $charge->id])->call('save')->assertHasErrors(['reason' => 'required']);

        expect($charge->fresh()->status)->toBe(ChargeStatus::Open);
    });
});

describe('send reminder window', function () {
    it('only explains when the primary contact has no valid e-mail', function () {
        $charge = Charge::factory()->create();

        Livewire::test(SendReminder::class, ['charge' => $charge->id])
            ->assertSee(__('reminders.no_recipient'))
            ->assertDontSee(__('reminders.send_confirm', ['email' => 'x']));
    });

    it('names the recipient and sends the reminder', function () {
        Mail::fake();

        $charge = renewalChargeToRemind();

        Livewire::test(SendReminder::class, ['charge' => $charge->id])
            ->assertSee(__('reminders.send_confirm', ['email' => 'ayse@example.com']))
            ->call('send')
            ->assertDispatched('charges-changed')
            ->assertDispatched('notify', message: __('reminders.sent_notification'));

        expect($charge->fresh()->reminder_sent_at)->not->toBeNull();
    });
});

// --- Ödeme ve kullanım ----------------------------------------------------------

describe('view payment window', function () {
    it('shows what the payment is, its allocations and a separate confirmed delete', function () {
        $customer = Customer::factory()->create(['short_name' => 'Logi']);
        $charge = Charge::factory()->for($customer)->create(['amount' => 100000, 'description' => 'Hosting', 'due_date' => '2026-07-01']);
        $payment = Payment::factory()->for($customer)->create(['amount' => 160000, 'paid_on' => '2026-06-10', 'note' => 'Havale geldi']);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'charge_id' => $charge->id, 'amount' => 100000]);

        $html = Livewire::test(ViewPayment::class, ['payment' => $payment->id])->html();

        expect($html)
            ->toContain('10.06.2026')
            ->toContain('Logi')
            ->toContain('1.600 TL')
            ->toContain('Havale geldi')
            ->toContain(__('payments.allocations_heading', ['count' => 1]))
            ->toContain('Hosting')
            ->toContain("action: 'delete-payment', id: {$payment->id}")
            ->toContain(__('actions.close'));
    });

    it('says how much went to the credit balance', function () {
        $payment = Payment::factory()->create(['amount' => 60000]);

        Livewire::test(ViewPayment::class, ['payment' => $payment->id])->assertSee(__('payments.result_credit', ['amount' => '600 TL']));
    });
});

describe('usage window', function () {
    it('says an unlinked item has no usage and offers no refresh', function () {
        $item = uiWindowService()->items()->firstOrFail();

        Livewire::test(ViewUsage::class, ['item' => $item->id])
            ->assertSee(__('usage.not_linked'))
            ->assertDontSee(__('usage.refresh'));
    });
});
