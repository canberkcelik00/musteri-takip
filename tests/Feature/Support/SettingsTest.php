<?php

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->settings = app(Settings::class);
});

it('returns the defaults while nothing is stored', function () {
    Log::spy();

    expect($this->settings->get(SettingKey::RenewalLeadDays))->toBe('30')
        ->and($this->settings->get(SettingKey::DigestTime))->toBe('09:00')
        ->and($this->settings->get(SettingKey::NotificationEmail))->toBeNull()
        ->and($this->settings->renewalLeadDays())->toBe(30)
        ->and($this->settings->digestTime())->toBe('09:00')
        ->and($this->settings->notificationEmail())->toBeNull();

    Log::shouldNotHaveReceived('warning');
});

it('returns the stored values', function () {
    Log::spy();

    $this->settings->set(SettingKey::RenewalLeadDays, '45');
    $this->settings->set(SettingKey::DigestTime, '18:30');
    $this->settings->set(SettingKey::NotificationEmail, 'sahip@example.com');

    expect($this->settings->get(SettingKey::RenewalLeadDays))->toBe('45')
        ->and($this->settings->renewalLeadDays())->toBe(45)
        ->and($this->settings->digestTime())->toBe('18:30')
        ->and($this->settings->notificationEmail())->toBe('sahip@example.com');

    Log::shouldNotHaveReceived('warning');
});

it('updates the stored value instead of adding a second row', function () {
    $this->settings->set(SettingKey::RenewalLeadDays, '45');
    $this->settings->set(SettingKey::RenewalLeadDays, '60');

    expect(Setting::count())->toBe(1)
        ->and($this->settings->renewalLeadDays())->toBe(60);
});

it('falls back to the default when the value is set to null', function () {
    $this->settings->set(SettingKey::DigestTime, '18:30');
    $this->settings->set(SettingKey::DigestTime, null);

    expect(Setting::count())->toBe(0)
        ->and($this->settings->digestTime())->toBe('09:00');
});

it('does not store the same key twice', function () {
    Setting::create(['key' => SettingKey::DigestTime->value, 'value' => '10:00']);

    expect(fn () => Setting::create(['key' => SettingKey::DigestTime->value, 'value' => '11:00']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('accepts the renewal lead day limits', function (string $days, int $expected) {
    Log::spy();

    $this->settings->set(SettingKey::RenewalLeadDays, $days);

    expect($this->settings->renewalLeadDays())->toBe($expected);
    Log::shouldNotHaveReceived('warning');
})->with([
    'lowest' => ['1', 1],
    'highest' => ['365', 365],
]);

it('falls back to 30 renewal lead days for an invalid value and logs it', function (string $days) {
    Log::spy();

    $this->settings->set(SettingKey::RenewalLeadDays, $days);

    expect($this->settings->renewalLeadDays())->toBe(30);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context === ['key' => 'renewal_lead_days', 'value' => $days],
    );
})->with(['0', '366', 'abc', '12.5']);

it('falls back to 09:00 digest time for an invalid value and logs it', function (string $time) {
    Log::spy();

    $this->settings->set(SettingKey::DigestTime, $time);

    expect($this->settings->digestTime())->toBe('09:00');
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context === ['key' => 'digest_time', 'value' => $time],
    );
})->with(['24:00', '9:00', '09:60', 'abc']);

it('falls back to no notification email for an invalid address and logs it', function () {
    Log::spy();

    $this->settings->set(SettingKey::NotificationEmail, 'gecersiz');

    expect($this->settings->notificationEmail())->toBeNull();
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context === ['key' => 'notification_email', 'value' => 'gecersiz'],
    );
});
