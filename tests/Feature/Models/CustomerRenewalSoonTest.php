<?php

use App\Enums\SettingKey;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
});

it('includes a customer whose free service is ending soon', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->free()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect(Customer::query()->renewalSoon()->pluck('id')->all())->toBe([$customer->id]);
});

it('excludes a customer whose only ending-soon service is cancelled', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect(Customer::query()->renewalSoon()->pluck('id')->all())->toBe([]);
});

it('excludes an archived customer', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $customer->archive();

    $active = Customer::factory()->create();
    CustomerService::factory()->for($active)->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $active->archive();

    expect(Customer::query()->renewalSoon()->pluck('id')->all())->toBe([]);
});

it('counts a customer with several ending-soon services once', function () {
    $customer = Customer::factory()->create();
    CustomerService::factory()->for($customer)->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    CustomerService::factory()->for($customer)->create(['start_date' => '2025-06-25', 'end_date' => '2026-06-25']);

    expect(Customer::query()->renewalSoon()->pluck('id')->all())->toBe([$customer->id]);
});
