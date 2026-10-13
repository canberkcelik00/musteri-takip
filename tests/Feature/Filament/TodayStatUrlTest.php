<?php

use App\Actions\Today\SummarizeToday;
use App\Enums\SettingKey;
use App\Filament\Resources\Charges\ChargeResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
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

/**
 * The charge list links of the "Bugün" page as the widgets render them: the three charge stats
 * and the two charge sections' "Tümünü gör".
 *
 * @return array<string, string>
 */
function todayChargeLinks(): array
{
    $stats = (fn () => $this->getStats())->call(new TodayStats);
    $tasks = (fn () => $this->getViewData())->call(new TodayTasks);

    return [
        'total' => $stats[0]->getUrl(),
        'overdue' => $stats[1]->getUrl(),
        'due_this_month' => $stats[2]->getUrl(),
        'overdue_section' => $tasks['overdueUrl'],
        'due_soon_section' => $tasks['dueSoonUrl'],
    ];
}

it('opens each charge list of the page without archived customers, as its number counts', function (string $link, array $seen, array $hidden) {
    $charges = [
        'overdue' => ['due_date' => '2026-06-01'],
        'due soon' => ['due_date' => '2026-06-18'],
        'later this month' => ['due_date' => '2026-06-25'],
        'next month' => ['due_date' => '2026-07-20'],
    ];

    foreach ($charges as $name => $attributes) {
        Charge::factory()->create([...$attributes, 'description' => "Borç: {$name}"]);
        Charge::factory()->for(Customer::factory()->archived())->create([...$attributes, 'description' => "Arşivli: {$name}"]);
    }

    Charge::factory()->paid()->create(['due_date' => '2026-06-20', 'description' => 'Ödenmiş borç']);

    $response = $this->get(todayChargeLinks()[$link])->assertOk()->assertDontSee('Ödenmiş borç');

    foreach ($seen as $name) {
        $response->assertSee("Borç: {$name}");
    }

    foreach ([...$hidden, ...array_keys($charges)] as $name) {
        if (! in_array($name, $seen, true)) {
            $response->assertDontSee("Borç: {$name}");
        }
    }

    foreach (array_keys($charges) as $name) {
        $response->assertDontSee("Arşivli: {$name}");
    }

    $summary = app(SummarizeToday::class)->handle();
    $counts = [
        'total' => count($charges),
        'overdue' => $summary['overdue_count'],
        'due_this_month' => $summary['due_this_month_count'],
    ];

    if (array_key_exists($link, $counts)) {
        expect($counts[$link])->toBe(count($seen));
    }
})->with([
    'total open debt' => ['total', ['overdue', 'due soon', 'later this month', 'next month'], []],
    'overdue' => ['overdue', ['overdue'], []],
    'due this month' => ['due_this_month', ['due soon', 'later this month'], []],
    'overdue section' => ['overdue_section', ['overdue'], []],
    'due soon section' => ['due_soon_section', ['due soon'], []],
]);

it('keeps the archived customers in the list opened from the menu', function () {
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-06-01', 'description' => 'Arşivli müşterinin borcu']);

    $this->get(ChargeResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Arşivli müşterinin borcu');
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

    expect($stats[0]->getUrl())->toContain('filters%5Bhide_archived%5D%5BisActive%5D=1')
        ->and($stats[1]->getUrl())->toContain('filters%5Bonly_overdue%5D%5BisActive%5D=1')
        ->and($stats[1]->getUrl())->toContain('filters%5Bhide_archived%5D%5BisActive%5D=1')
        ->and($stats[2]->getUrl())->toContain('filters%5Bdue_this_month%5D%5BisActive%5D=1')
        ->and($stats[2]->getUrl())->toContain('filters%5Bhide_archived%5D%5BisActive%5D=1')
        ->and($stats[3]->getUrl())->toContain('filters%5Brenewal_soon%5D%5BisActive%5D=1');
});
