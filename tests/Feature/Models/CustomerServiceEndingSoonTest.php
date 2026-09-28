<?php

use App\Enums\SettingKey;
use App\Models\CustomerService;
use App\Support\Settings;

beforeEach(function () {
    $this->travelTo('2026-06-15 10:00:00');
});

it('treats an active service ending within the default lead time as ending soon', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');

    $endsToday = CustomerService::factory()->create(['start_date' => '2025-06-15', 'end_date' => '2026-06-15']);
    $endsInLeadTime = CustomerService::factory()->create(['start_date' => '2025-07-15', 'end_date' => '2026-07-15']);

    expect($endsToday->isEndingSoon())->toBeTrue()
        ->and($endsInLeadTime->isEndingSoon())->toBeTrue();
});

it('does not treat a service ending after the lead time as ending soon', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');

    $service = CustomerService::factory()->create(['start_date' => '2025-07-16', 'end_date' => '2026-07-16']);

    expect($service->isEndingSoon())->toBeFalse();
});

it('does not treat a cancelled service as ending soon', function () {
    $service = CustomerService::factory()->cancelled()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect($service->isEndingSoon())->toBeFalse();
});

it('uses an explicit lead time over the stored setting', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');

    $service = CustomerService::factory()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);

    expect($service->isEndingSoon(3))->toBeFalse()
        ->and($service->isEndingSoon(10))->toBeTrue();
});

it('matches the endingSoon scope to the isEndingSoon method', function () {
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '10');

    $endingSoon = CustomerService::factory()->create(['start_date' => '2025-06-20', 'end_date' => '2026-06-20']);
    $tooFar = CustomerService::factory()->create(['start_date' => '2025-07-15', 'end_date' => '2026-07-15']);

    expect(CustomerService::query()->endingSoon()->pluck('id')->all())->toBe([$endingSoon->id])
        ->and($tooFar->isEndingSoon())->toBeFalse();
});
