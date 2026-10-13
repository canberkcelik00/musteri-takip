<?php

use App\Enums\SettingKey;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Widgets\TodayStats;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;

/**
 * Confirms the Stat links are not just internally consistent (TodayPageTest) but real URLs
 * that a browser visit actually filters by, the same query-string shape Filament's own
 * #[Url(as: 'filters')] binding on ListRecords uses.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

it('opens the overdue-filtered charge list from the stat url', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-01', 'description' => 'Gecikmiş borç']);
    $future = Charge::factory()->create(['due_date' => '2026-06-25', 'description' => 'Gelecek borç']);
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-06-01', 'description' => 'Arşivli müşterinin borcu']);

    $url = ChargeResource::getUrl('index', ['filters' => ['only_overdue' => ['isActive' => true]]]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Gecikmiş borç')
        ->assertDontSee('Gelecek borç')
        ->assertDontSee('Arşivli müşterinin borcu');
});

it('opens the renewal-filtered customer list from the stat url', function () {
    $renewing = Customer::factory()->create(['short_name' => 'Yenilenecek']);
    CustomerService::factory()->for($renewing)->create(['start_date' => '2025-06-10', 'end_date' => '2026-06-20']);

    $notRenewing = Customer::factory()->create(['short_name' => 'YenilenmeyecekMusteri']);
    CustomerService::factory()->for($notRenewing)->create(['start_date' => '2025-01-10', 'end_date' => '2027-01-10']);

    $url = CustomerResource::getUrl('index', ['filters' => ['renewal_soon' => ['isActive' => true]]]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Yenilenecek')
        ->assertDontSee('YenilenmeyecekMusteri');
});

it('generates the same stat urls the widget renders', function () {
    $widget = new TodayStats;
    $reflection = new ReflectionMethod($widget, 'getStats');
    $reflection->setAccessible(true);
    $stats = $reflection->invoke($widget);

    expect($stats[1]->getUrl())->toContain('filters%5Bonly_overdue%5D%5BisActive%5D=1')
        ->and($stats[2]->getUrl())->toContain('filters%5Bdue_this_month%5D%5BisActive%5D=1')
        ->and($stats[3]->getUrl())->toContain('filters%5Brenewal_soon%5D%5BisActive%5D=1');
});
