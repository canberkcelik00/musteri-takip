<?php

use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Enums\SettingKey;
use App\Filament\Pages\ManageSettings;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('panel');
});

it('opens with the default values', function () {
    Livewire::test(ManageSettings::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'renewal_lead_days' => 30,
            'digest_time' => '09:00',
            'notification_email' => null,
        ]);
});

it('opens with the stored values', function () {
    $settings = app(Settings::class);
    $settings->set(SettingKey::RenewalLeadDays, '45');
    $settings->set(SettingKey::DigestTime, '18:30');
    $settings->set(SettingKey::NotificationEmail, 'sahip@example.com');

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet([
            'renewal_lead_days' => 45,
            'digest_time' => '18:30',
            'notification_email' => 'sahip@example.com',
        ]);
});

it('shows the page in Turkish under the settings group', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Genel ayarlar')
        ->assertSee('Yenileme gün sayısı')
        ->assertSee('Günlük özet saati')
        ->assertSee('Bildirim adresi');

    expect(ManageSettings::getNavigationGroup())->toBe('Ayarlar');
});

it('saves the settings', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm([
            'renewal_lead_days' => '60',
            'digest_time' => '08:15',
            'notification_email' => 'sahip@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Ayarlar kaydedildi.');

    $settings = app(Settings::class);

    expect($settings->renewalLeadDays())->toBe(60)
        ->and($settings->digestTime())->toBe('08:15')
        ->and($settings->notificationEmail())->toBe('sahip@example.com');
});

it('returns to the default address when the address is cleared', function () {
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    Livewire::test(ManageSettings::class)
        ->fillForm(['notification_email' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->notificationEmail())->toBeNull();
});

it('shows form errors and saves nothing for invalid values', function (array $invalid, string $field) {
    Livewire::test(ManageSettings::class)
        ->fillForm($invalid)
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(app(Settings::class)->renewalLeadDays())->toBe(30)
        ->and(app(Settings::class)->digestTime())->toBe('09:00');
})->with([
    'no days' => [['renewal_lead_days' => null], 'renewal_lead_days'],
    'zero days' => [['renewal_lead_days' => 0], 'renewal_lead_days'],
    'too many days' => [['renewal_lead_days' => 366], 'renewal_lead_days'],
    'no time' => [['digest_time' => null], 'digest_time'],
    'time off the quarter hour' => [['digest_time' => '09:07'], 'digest_time'],
    'invalid address' => [['notification_email' => 'gecersiz'], 'notification_email'],
]);

it('applies a changed renewal lead day count to the renewal rules at once', function () {
    $service = CustomerService::factory()->create([
        'price' => 400000,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    // 40 days before the end; the default window (30 days) has not started.
    $this->travelTo('2027-01-20 10:00:00');
    expect(app(CreateDueRenewalCharges::class)->handle())->toBeEmpty();

    Livewire::test(ManageSettings::class)
        ->fillForm(['renewal_lead_days' => 45])
        ->call('save')
        ->assertHasNoFormErrors();

    $charges = app(CreateDueRenewalCharges::class)->handle();

    expect($charges)->toHaveCount(1)
        ->and($charges->first()->customer_service_id)->toBe($service->id)
        ->and($charges->first()->due_date->toDateString())->toBe('2027-03-01')
        ->and(Charge::query()->count())->toBe(1);
});

it('applies a shortened renewal lead day count to the renewal rules at once', function () {
    $service = CustomerService::factory()->create([
        'price' => 400000,
        'start_date' => '2026-03-01',
        'end_date' => '2027-03-01',
    ]);

    app(Settings::class)->set(SettingKey::RenewalLeadDays, '45');

    // 40 days before the end; inside the 45 day window, outside a 20 day one.
    $this->travelTo('2027-01-20 10:00:00');

    Livewire::test(ManageSettings::class)
        ->fillForm(['renewal_lead_days' => 20])
        ->call('save');

    expect(app(CreateDueRenewalCharges::class)->handle())->toBeEmpty()
        ->and($service->charges()->count())->toBe(0);
});
