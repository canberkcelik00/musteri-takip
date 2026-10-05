<?php

use App\Filament\Actions\CreateCustomerAction;
use App\Filament\Actions\CreateManualChargeAction;
use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\CatalogServices\CatalogServiceResource;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Livewire\TopbarActions;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('shows the topbar actions component on every panel page, in the same place', function () {
    $customer = Customer::factory()->create();

    $urls = [
        'Bugün' => Dashboard::getUrl(),
        'Müşteriler' => CustomerResource::getUrl('index'),
        'Müşteri kartı' => CustomerResource::getUrl('view', ['record' => $customer]),
        'Açık borçlar' => ChargeResource::getUrl('index'),
        'Ödemeler' => PaymentResource::getUrl('index'),
        'Hizmetler' => CatalogServiceResource::getUrl('index'),
        'Paketler' => PackageResource::getUrl('index'),
        'Ödeme yöntemleri' => PaymentMethodResource::getUrl('index'),
        'Genel ayarlar' => ManageSettings::getUrl(),
        // The profile page is Filament's simple, sidebar-less layout (an account settings page,
        // not one of docs/UI.md §3's menu screens), so it has no topbar of its own.
    ];

    foreach ($urls as $label => $url) {
        $this->get($url)
            ->assertOk()
            ->assertSeeLivewire(TopbarActions::class)
            ->assertSeeInOrder([
                'fi-global-search-ctn',
                'data-topbar-actions',
                'Ödeme al',
                'Yeni',
                'fi-user-menu-trigger',
            ], escape: false);
    }
});

it('records a payment through the topbar with only the customer and the amount', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 50000, 'due_date' => '2026-06-01']);

    $this->travelTo('2026-06-15 10:00:00');

    Livewire::test(TopbarActions::class)
        ->assertActionExists('recordPayment')
        ->callAction('recordPayment', [
            'customer_id' => $customer->id,
            'amount' => '500',
        ])
        ->assertDispatched(RecordPaymentAction::PAYMENTS_CHANGED_EVENT);

    $payment = $customer->payments()->sole();

    expect($payment->paid_on->toDateString())->toBe('2026-06-15')
        ->and($charge->refresh()->allocatedAmount())->toBe(50000);
});

it('opens the payment form in the form window from the topbar', function () {
    Livewire::test(TopbarActions::class)
        ->mountAction('recordPayment')
        ->assertActionMounted('recordPayment');

    expect(RecordPaymentAction::make()->isModalSlideOver())->toBeFalse()
        ->and(RecordPaymentAction::make()->getModalWidth())->toBe(AppServiceProvider::FORM_WINDOW_CLASS);
});

it('lets the "Yeni" menu create an unattached manual charge for a chosen customer', function () {
    $customer = Customer::factory()->create();

    Livewire::test(TopbarActions::class)
        ->callAction('createManualCharge', [
            'customer_id' => $customer->id,
            'description' => 'Kurulum',
            'amount' => '500',
            'currency' => 'TRY',
            'due_date' => '2026-06-30',
        ])
        ->assertDispatched(CreateManualChargeAction::CHARGES_CHANGED_EVENT);

    expect($customer->charges()->sole()->description)->toBe('Kurulum');
});

it('lets the "Yeni" menu create a customer in the form window and opens its card', function () {
    Livewire::test(TopbarActions::class)
        ->mountAction('createCustomer')
        ->assertActionMounted('createCustomer')
        ->fillForm(['short_name' => 'Yeni Müşteri', 'contact' => ['name' => 'Ayşe Kaya']])
        ->callMountedAction()
        ->assertRedirect(CustomerResource::getUrl('view', ['record' => Customer::query()->sole()]));

    expect(CreateCustomerAction::make()->isModalSlideOver())->toBeFalse()
        ->and(CreateCustomerAction::make()->getModalWidth())->toBe(AppServiceProvider::FORM_WINDOW_CLASS)
        ->and(Customer::query()->sole()->primaryContact->name)->toBe('Ayşe Kaya');
});
