<?php

use App\Actions\Today\ListTodayTasks;
use App\Enums\SettingKey;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

function listTodayTasks(int $limit = 10): array
{
    return app(ListTodayTasks::class)->handle($limit);
}

it('lists overdue charges oldest due date first', function () {
    $recent = Charge::factory()->create(['due_date' => '2026-06-10']);
    $oldest = Charge::factory()->create(['due_date' => '2026-05-01']);
    Charge::factory()->create(['due_date' => '2026-06-20']); // not overdue

    $tasks = listTodayTasks();

    expect($tasks['overdue']['items']->pluck('id')->all())->toBe([$oldest->id, $recent->id])
        ->and($tasks['overdue']['total'])->toBe(2);
});

it('leaves the overdue charges of archived customers out', function () {
    $overdue = Charge::factory()->create(['due_date' => '2026-06-10']);
    Charge::factory()->for(Customer::factory()->archived())->create(['due_date' => '2026-06-01']);

    $tasks = listTodayTasks();

    expect($tasks['overdue']['items']->pluck('id')->all())->toBe([$overdue->id])
        ->and($tasks['overdue']['total'])->toBe(1);
});

it('lists charges due within 7 days separately from overdue ones', function () {
    $dueSoon = Charge::factory()->create(['due_date' => '2026-06-18']);
    Charge::factory()->create(['due_date' => '2026-06-10']); // overdue, not due_soon
    Charge::factory()->create(['due_date' => '2026-06-25']); // too far

    $tasks = listTodayTasks();

    expect($tasks['due_soon']['items']->pluck('id')->all())->toBe([$dueSoon->id])
        ->and($tasks['due_soon']['total'])->toBe(1);
});

it('lists services awaiting a free-continuation decision, excluding archived customers', function () {
    $customer = Customer::factory()->create();
    $service = CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    $archivedCustomer = Customer::factory()->create();
    CustomerService::factory()->for($archivedCustomer)->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $archivedCustomer->archive();
    CustomerService::factory()->for($archivedCustomer)->free()->create(['start_date' => '2025-06-21', 'end_date' => '2026-06-21']);

    $tasks = listTodayTasks();

    expect($tasks['free_decisions']['items']->pluck('id')->all())->toBe([$service->id])
        ->and($tasks['free_decisions']['total'])->toBe(1);
});

it('counts a customer with two awaiting services once in the total but lists both rows, without needing a "view all" link', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-21', 'end_date' => '2026-06-21']);

    $tasks = listTodayTasks();

    expect($tasks['free_decisions']['items'])->toHaveCount(2)
        ->and($tasks['free_decisions']['total'])->toBe(1)
        ->and($tasks['free_decisions']['row_total'])->toBe(2);
});

it('caps free-decision rows while counting distinct customers across all rows', function () {
    $customers = Customer::factory()->count(10)->create();

    foreach ($customers as $index => $customer) {
        CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    }

    // An 11th service for the first customer: 11 rows, still 10 distinct customers.
    CustomerService::factory()->for($customers->first())->free()->create(['start_date' => '2025-06-21', 'end_date' => '2026-06-21']);

    $tasks = listTodayTasks();

    expect($tasks['free_decisions']['items'])->toHaveCount(10)
        ->and($tasks['free_decisions']['row_total'])->toBe(11)
        ->and($tasks['free_decisions']['total'])->toBe(10);
});

it('caps each section at the given limit while total counts everything', function () {
    Charge::factory()->count(3)->sequence(
        ['due_date' => '2026-06-01'],
        ['due_date' => '2026-06-02'],
        ['due_date' => '2026-06-03'],
    )->create();

    $tasks = listTodayTasks(2);

    expect($tasks['overdue']['items'])->toHaveCount(2)
        ->and($tasks['overdue']['total'])->toBe(3);
});
