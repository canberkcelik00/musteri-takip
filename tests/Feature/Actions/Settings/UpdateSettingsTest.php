<?php

use App\Actions\Settings\UpdateSettings;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $data
 */
function updateSettings(array $data): void
{
    app(UpdateSettings::class)->handle([
        'renewal_lead_days' => 30,
        'digest_time' => '09:00',
        'notification_email' => null,
        ...$data,
    ]);
}

it('stores the three settings', function () {
    updateSettings(['renewal_lead_days' => '45', 'digest_time' => '18:30', 'notification_email' => 'sahip@example.com']);

    $settings = app(Settings::class);

    expect($settings->renewalLeadDays())->toBe(45)
        ->and($settings->digestTime())->toBe('18:30')
        ->and($settings->notificationEmail())->toBe('sahip@example.com');
});

it('removes the notification address when it is blank', function () {
    updateSettings(['notification_email' => 'sahip@example.com']);
    updateSettings(['notification_email' => '  ']);

    expect(Setting::query()->where('key', SettingKey::NotificationEmail->value)->exists())->toBeFalse()
        ->and(app(Settings::class)->notificationEmail())->toBeNull();
});

it('trims the notification address', function () {
    updateSettings(['notification_email' => ' sahip@example.com ']);

    expect(app(Settings::class)->notificationEmail())->toBe('sahip@example.com');
});

it('accepts the renewal lead day limits', function (int $days) {
    updateSettings(['renewal_lead_days' => $days]);

    expect(app(Settings::class)->renewalLeadDays())->toBe($days);
})->with([1, 365]);

it('rejects an invalid value and writes nothing', function (array $invalid, string $field) {
    updateSettings(['renewal_lead_days' => '45']);
    $stored = Setting::query()->pluck('value', 'key')->all();

    expect(fn () => updateSettings($invalid))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));

    expect(Setting::query()->pluck('value', 'key')->all())->toBe($stored)
        ->and(app(Settings::class)->renewalLeadDays())->toBe(45);
})->with([
    'zero days' => [['renewal_lead_days' => 0], 'renewal_lead_days'],
    'too many days' => [['renewal_lead_days' => 366], 'renewal_lead_days'],
    'text days' => [['renewal_lead_days' => 'abc'], 'renewal_lead_days'],
    'decimal days' => [['renewal_lead_days' => '12.5'], 'renewal_lead_days'],
    'no days' => [['renewal_lead_days' => null], 'renewal_lead_days'],
    'time off the quarter hour' => [['digest_time' => '09:07'], 'digest_time'],
    'time out of range' => [['digest_time' => '24:00'], 'digest_time'],
    'no time' => [['digest_time' => null], 'digest_time'],
    'invalid address' => [['notification_email' => 'gecersiz'], 'notification_email'],
]);

it('offers every quarter hour of the day', function () {
    $options = UpdateSettings::digestTimeOptions();

    expect($options)->toHaveCount(96)
        ->and(array_key_first($options))->toBe('00:00')
        ->and(array_key_last($options))->toBe('23:45')
        ->and($options)->toHaveKeys(['09:00', '09:15', '12:30']);
});
