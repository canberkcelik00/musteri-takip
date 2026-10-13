<?php

use App\Actions\Payments\RecordPayment;
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

    // Overdue: 70000, 1 charge; the archived customer's is left out — matches the only_overdue list.
    expect($summary['overdue']['TRY']->amount)->toBe(70000)
        ->and($summary['overdue_count'])->toBe(1);
    Livewire::test(ListCharges::class)->filterTable('only_overdue', true)
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$archivedCharge, $openFuture]);

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

it('counts renewing services, not customers, and shows the nearest end date; the link still opens their customers (G-4B.6)', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '45');

    $twoServices = Customer::factory()->create();
    CustomerService::factory()->for($twoServices)->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    CustomerService::factory()->for($twoServices)->free()->create(['start_date' => '2025-07-25', 'end_date' => '2026-07-25']);
    $oneService = Customer::factory()->create();
    CustomerService::factory()->for($oneService)->create(['start_date' => '2025-06-18', 'end_date' => '2026-06-18']);

    // Outside the 45 day window, cancelled, or owned by an archived customer: not counted.
    CustomerService::factory()->for($oneService)->create(['start_date' => '2025-08-15', 'end_date' => '2026-08-15']);
    CustomerService::factory()->for($oneService)->cancelled()->create(['start_date' => '2025-06-19', 'end_date' => '2026-06-19']);
    $archived = Customer::factory()->create();
    CustomerService::factory()->for($archived)->create(['start_date' => '2025-06-16', 'end_date' => '2026-06-16']);
    $archived->archive();

    $summary = app(SummarizeToday::class)->handle();

    expect($summary['renewal_services_count'])->toBe(3)
        ->and($summary['nearest_renewal']->toDateString())->toBe('2026-06-18')
        ->and($summary['renewal_customers_count'])->toBe(2)
        ->and($summary['renewal_lead_days'])->toBe(45);

    Livewire::test(TodayStats::class)
        ->assertSee('45 gün içinde yenilenecek')
        ->assertSee('3 hizmet')
        ->assertSee('En yakını 18.06.2026');

    Livewire::test(ListCustomers::class)->filterTable('renewal_soon', true)
        ->assertCanSeeTableRecords([$twoServices, $oneService])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('shows the charge counts under the overdue and due-this-month totals, and other currencies under the total (G-4B.6)', function () {
    Charge::factory()->create(['due_date' => '2026-06-01', 'amount' => 70000]); // overdue
    Charge::factory()->create(['due_date' => '2026-06-20', 'amount' => 30000]); // due this month
    Charge::factory()->create(['due_date' => '2026-06-30', 'amount' => 12000, 'currency' => Currency::Usd]); // due this month
    Charge::factory()->create(['due_date' => '2026-07-10', 'amount' => 50000]); // next month

    $summary = app(SummarizeToday::class)->handle();

    expect($summary['overdue_count'])->toBe(1)
        ->and($summary['due_this_month_count'])->toBe(2);

    Livewire::test(TodayStats::class)
        ->assertSeeInOrder([__('today.total_debt'), '1.500 TL', '+ 120 USD'])
        ->assertSeeInOrder([__('today.overdue'), '700 TL', '1 borç'])
        ->assertSeeInOrder([__('today.due_this_month'), '300 TL + 120 USD', '2 borç']);
});

it('leaves the lines below the numbers out when there is nothing to count (G-4B.6)', function () {
    $summary = app(SummarizeToday::class)->handle();

    expect($summary['due_this_month_count'])->toBe(0)
        ->and($summary['renewal_services_count'])->toBe(0)
        ->and($summary['nearest_renewal'])->toBeNull();

    Livewire::test(TodayStats::class)
        ->assertSee('0 hizmet')
        ->assertDontSee('En yakını')
        ->assertDontSee('+ ');
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

it('does not list an archived customer\'s overdue charge', function () {
    $customer = Customer::factory()->create();
    Charge::factory()->for($customer)->create(['due_date' => '2026-06-01', 'amount' => 50000]);
    $customer->archive();

    Livewire::test(TodayTasks::class)
        ->assertDontSee($customer->short_name)
        ->assertDontSee(__('payments.record'));
});

it('lists the three kinds of work as the rows of one table, in priority order, with the count in the heading', function () {
    $overdue = Customer::factory()->create(['short_name' => 'Aaa Gecikmis']);
    $dueSoon = Customer::factory()->create(['short_name' => 'Bbb Yaklasan']);
    Charge::factory()->for($dueSoon)->create(['due_date' => '2026-06-18', 'amount' => 50000]);
    Charge::factory()->for($overdue)->create(['due_date' => '2026-06-01', 'amount' => 100000]);
    $free = CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(TodayTasks::class)
        ->assertSeeInOrder(['Aaa Gecikmis', 'Bbb Yaklasan', $free->customer->short_name])
        ->assertSee(__('today.tasks_count', ['count' => 3]))
        ->assertSee([__('today.col_customer'), __('today.col_subject'), __('today.col_due'), __('today.col_status'), __('today.col_amount')])
        ->html();

    expect(substr_count($html, '<table'))->toBe(1);
});

it('shows "Karar bekliyor" as plain text, not as a badge, and "—" instead of an amount', function () {
    CustomerService::factory()->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $html = Livewire::test(TodayTasks::class)
        ->assertSee(__('today.awaiting_decision'))
        ->assertSee(__('money.empty'))
        ->html();

    // The only thing on the page is a free service: nothing in it is a colored (or any) badge.
    expect($html)->not->toContain('fi-badge');
});

it('puts "kısmi ödendi" after a partly paid charge and "ücretsiz" after a free service, in the subject', function () {
    $customer = Customer::factory()->create();
    $charge = Charge::factory()->for($customer)->create(['due_date' => '2026-06-01', 'amount' => 100000, 'description' => 'Hosting Pro']);
    app(RecordPayment::class)->handle($customer, [
        'amount' => 40000,
        'currency' => $charge->currency,
        'paid_on' => '2026-06-15',
        'payment_method_id' => PaymentMethod::query()->firstOrFail()->id,
    ], [$charge->id => 40000]);
    $service = CustomerService::factory()->free()->create(['name' => 'Hosting Baslangic', 'start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    Livewire::test(TodayTasks::class)
        ->assertSee('Hosting Pro · '.__('today.partially_paid_suffix'))
        ->assertSee($service->name.' · '.__('today.free_suffix'));
});

it('colors the status badge red for overdue and amber for due soon', function () {
    Charge::factory()->create(['due_date' => '2026-06-01', 'amount' => 100000]);
    $html = Livewire::test(TodayTasks::class)->html();

    expect($html)->toContain('fi-color-danger')->not->toContain('fi-color-warning');

    Charge::query()->delete();
    Charge::factory()->create(['due_date' => '2026-06-18', 'amount' => 100000]);
    $html = Livewire::test(TodayTasks::class)->html();

    expect($html)->toContain('fi-color-warning')->not->toContain('fi-color-danger');
});

it('moves the state under the customer name below md, where the "Durum" column is hidden', function () {
    Charge::factory()->create(['due_date' => '2026-06-01', 'amount' => 100000]);

    $html = Livewire::test(TodayTasks::class)->html();

    // The column and its header are shown from `md` up; below it the badge sits in the name cell.
    expect($html)->toMatch('/<th class="fi-ta-header-cell fi-cell-from-md">\s*'.preg_quote(__('today.col_status'), '/').'/')
        ->and($html)->toContain('fi-mobile-badge')
        ->and(substr_count($html, 'fi-color-danger'))->toBe(2);
});
