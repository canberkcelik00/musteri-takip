<?php

use App\Enums\ServiceType;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
use App\Livewire\TopbarActions;
use App\Models\CatalogService;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * docs/UI.md §7's remaining click-count targets, as far as they are not already counted in the
 * test that owns the flow: Bugün's row payment (TodayPageTest), the topbar payment form's
 * customer + amount only (TopbarTest), the card's service form (CustomerListAndCardTest).
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
});

it('opens /panel straight on Bugün with both widgets, with zero clicks', function () {
    expect(Dashboard::getUrl())->toBe(url('/panel'));

    $this->get('/panel')
        ->assertOk()
        ->assertSeeLivewire(TodayStats::class)
        ->assertSeeLivewire(TodayTasks::class);
});

it('binds Ctrl+K on the panel and opens the Logi card from the "logi.com.tr" result in one click', function () {
    expect(Filament::getCurrentPanel()->getGlobalSearchKeyBindings())->toBe(['mod+k']);

    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    $service = CustomerService::factory()
        ->for($customer)
        ->forCatalogService(CatalogService::factory()->create(['type' => ServiceType::Hosting]))
        ->create();
    $service->items()->first()->update(['details' => ['domain' => 'logi.com.tr']]);

    $result = CustomerResource::getGlobalSearchResults('logi.com.tr')->sole();

    expect($result->url)->toBe(CustomerResource::getUrl('view', ['record' => $customer]));
});

it('shows "Ödeme al" on Bugün, Müşteriler and a source page, where customer + amount is all the form needs', function () {
    foreach ([Dashboard::getUrl(), CustomerResource::getUrl('index'), PaymentResource::getUrl('index')] as $url) {
        $this->get($url)->assertOk()->assertSee('Ödeme al');
    }

    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['amount' => 50000, 'due_date' => '2026-06-01']);

    Livewire::test(TopbarActions::class)
        ->callAction('recordPayment', ['customer_id' => $customer->id, 'amount' => '500'])
        ->assertHasNoErrors();

    expect($charge->refresh()->allocatedAmount())->toBe(50000);
});

it('goes from the overdue stat card to the filtered list in one click, the filter staying visible as a chip', function () {
    Charge::factory()->create(['due_date' => '2026-06-01', 'description' => 'Gecikmiş borç']);
    Charge::factory()->create(['due_date' => '2026-06-25', 'description' => 'Gelecek borç']);

    $widget = new TodayStats;
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    parse_str((string) parse_url($stats[1]->getUrl(), PHP_URL_QUERY), $query);

    $list = Livewire::withQueryParams($query)->test(ListCharges::class);

    $indicators = collect($list->instance()->getTable()->getFilterIndicators())
        ->map(fn ($indicator) => $indicator->getLabel());

    expect($indicators->all())->toContain(__('charges.only_overdue'));

    $list->assertSee('Gecikmiş borç')->assertDontSee('Gelecek borç');
});
