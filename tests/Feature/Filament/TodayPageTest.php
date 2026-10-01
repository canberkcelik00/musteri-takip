<?php

use App\Actions\Today\SummarizeToday;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Enums\SettingKey;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TodayTasks;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

it('shows both widgets on the Bugün page', function () {
    $this->get(Dashboard::getUrl())
        ->assertOk()
        ->assertSeeLivewire(TodayStats::class)
        ->assertSeeLivewire(TodayTasks::class);
});

it('shows the empty state when there is nothing to do', function () {
    Livewire::test(TodayTasks::class)
        ->assertSee(__('today.empty'));
});

it('matches each stat to the list its link opens', function () {
    $archivedCustomer = Customer::factory()->create();
    Charge::factory()->for($archivedCustomer)->create(['due_date' => '2026-06-10', 'amount' => 50000]); // overdue
    $archivedCustomer->archive();

    $openFuture = Charge::factory()->create(['due_date' => '2026-06-30', 'amount' => 30000]); // due this month
    $overdue = Charge::factory()->create(['due_date' => '2026-06-01', 'amount' => 70000]);

    $renewingCustomer = Customer::factory()->create();
    CustomerService::factory()->for($renewingCustomer)->create(['start_date' => '2025-06-10', 'end_date' => '2026-06-20']);

    $archivedCharge = $archivedCustomer->charges()->firstOrFail();
    $summary = app(SummarizeToday::class)->handle();

    // Total open debt: 50000 (archived) + 30000 + 70000 = 150000.
    expect($summary['total']['TRY']->amount)->toBe(150000);
    Livewire::test(ListCharges::class)
        ->assertCanSeeTableRecords([$archivedCharge, $overdue, $openFuture]);

    // Overdue: 50000 (archived) + 70000 = 120000, 2 charges — matches the only_overdue list.
    expect($summary['overdue']['TRY']->amount)->toBe(120000)
        ->and($summary['overdue_count'])->toBe(2);
    Livewire::test(ListCharges::class)->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$archivedCharge, $overdue])
        ->assertCanNotSeeTableRecords([$openFuture]);

    // Due this month: 30000 — matches the due_this_month list.
    expect($summary['due_this_month']['TRY']->amount)->toBe(30000);
    Livewire::test(ListCharges::class)->filterTable('due_this_month', true)
        ->assertCanSeeTableRecords([$openFuture])
        ->assertCanNotSeeTableRecords([$overdue, $archivedCharge]);

    // Renewing customers: 1 — matches the renewal_soon list.
    expect($summary['renewal_customers_count'])->toBe(1);
    Livewire::test(ListCustomers::class)->filterTable('renewal_soon', true)
        ->assertCanSeeTableRecords([$renewingCustomer]);
});

it('caps the overdue section at 10 rows and links to the full filtered list', function () {
    Charge::factory()->count(11)->sequence(
        ...collect(range(0, 10))->map(fn (int $i): array => ['due_date' => Carbon::parse('2026-05-01')->addDays($i)])->all(),
    )->create();

    Livewire::test(TodayTasks::class)
        ->assertSee(__('today.view_all', ['count' => 11]));
});

it('lists services awaiting a free-continuation decision and links to the filtered customer list', function () {
    $customer = Customer::factory()->create(['short_name' => 'Logi']);
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-21', 'end_date' => '2026-06-21']);

    $widget = Livewire::test(TodayTasks::class);

    $widget->assertSee('Logi')
        ->assertDontSee(__('today.view_all', ['count' => 1]));

    Livewire::test(ListCustomers::class)
        ->filterTable('awaiting_free_decision', true)
        ->assertCanSeeTableRecords([$customer]);
});

it('records a payment from an overdue row in one click plus the amount', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['due_date' => '2026-06-01', 'amount' => 100000]);
    PaymentMethod::query()->firstOrFail();

    Livewire::test(TodayTasks::class)
        ->mountAction('recordPayment', ['customer' => $customer->id, 'currency' => $charge->currency->value])
        ->assertSchemaStateSet(['customer_id' => $customer->id, 'currency' => Currency::Try])
        ->fillForm(['amount' => '1.000'])
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid);
});

it('records a payment from a due-soon row in one click plus the amount', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['due_date' => '2026-06-18', 'amount' => 50000]);

    Livewire::test(TodayTasks::class)
        ->mountAction('recordPayment', ['customer' => $customer->id, 'currency' => $charge->currency->value])
        ->assertSchemaStateSet(['customer_id' => $customer->id, 'currency' => Currency::Try])
        ->fillForm(['amount' => '500'])
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid);
});

it('confirms free continuation from the row action, removing it from the list', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    Livewire::test(TodayTasks::class)
        ->mountAction('confirmFreeContinuation', ['customerService' => $service->id])
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($service->fresh()->isAwaitingFreeDecision())->toBeFalse();
});

it('makes a service paid from the row action, creating a charge', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20', 'price' => 400000]);

    Livewire::test(TodayTasks::class)
        ->mountAction('removeFree', ['customerService' => $service->id])
        ->fillForm(['price' => '4.000'])
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($service->fresh()->is_free)->toBeFalse()
        ->and($service->fresh()->charges()->exists())->toBeTrue();
});

it('renders row buttons that send their record id with the click', function () {
    $service = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => '2026-06-01', 'amount' => 100000]);

    $html = Livewire::test(TodayTasks::class)->html();

    expect($html)
        ->toContain("mountAction('confirmFreeContinuation', JSON.parse('{\u0022customerService\u0022:{$service->id}}')")
        ->toContain("mountAction('removeFree', JSON.parse('{\u0022customerService\u0022:{$service->id}}')")
        ->toContain("mountAction('recordPayment', JSON.parse('{\u0022customer\u0022:{$customer->id},\u0022currency\u0022:\u0022TRY\u0022}')");
});

it('does not offer "Ödeme al" for an archived customer\'s overdue charge', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => '2026-06-01', 'amount' => 50000]);
    $customer->archive();

    Livewire::test(TodayTasks::class)
        ->assertSee($customer->short_name) // still listed, per docs/UI.md's "sayı = liste"
        ->assertDontSee(__('payments.record'));
});
