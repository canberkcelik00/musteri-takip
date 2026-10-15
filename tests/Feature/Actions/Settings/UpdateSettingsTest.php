<?php

use App\Actions\Settings\UpdateSettings;
use App\Enums\DigestFrequency;
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
        'digest_frequency' => 'monthly',
        'digest_day_of_month' => 15,
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

it('stores the digest frequency and the day of the month', function () {
    updateSettings(['digest_frequency' => 'monthly', 'digest_day_of_month' => '28']);

    expect(app(Settings::class)->digestFrequency())->toBe(DigestFrequency::Monthly)
        ->and(app(Settings::class)->digestDayOfMonth())->toBe(28);

    updateSettings(['digest_frequency' => DigestFrequency::Daily, 'digest_day_of_month' => null]);

    expect(app(Settings::class)->digestFrequency())->toBe(DigestFrequency::Daily)
        ->and(app(Settings::class)->digestDayOfMonth())->toBe(28);
});

it('requires a day of the month from 1 to 28 for the monthly digest', function (mixed $day) {
    expect(fn () => updateSettings(['digest_frequency' => 'monthly', 'digest_day_of_month' => $day]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('digest_day_of_month'));

    expect(Setting::query()->count())->toBe(0);
})->with([
    'no day' => [null],
    'zero' => [0],
    'day 29' => [29],
    'day 31' => [31],
    'text' => ['abc'],
]);

it('accepts the day of the month limits', function (int $day) {
    updateSettings(['digest_frequency' => 'monthly', 'digest_day_of_month' => $day]);

    expect(app(Settings::class)->digestDayOfMonth())->toBe($day);
})->with([1, 28]);

it('rejects an unknown digest frequency', function (mixed $frequency) {
    expect(fn () => updateSettings(['digest_frequency' => $frequency]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('digest_frequency'));
})->with(['weekly', null]);

it('stores the customer reminders switch', function () {
    updateSettings(['customer_reminders_enabled' => true]);

    expect(app(Settings::class)->customerRemindersEnabled())->toBeTrue();

    updateSettings(['customer_reminders_enabled' => false]);

    expect(app(Settings::class)->customerRemindersEnabled())->toBeFalse();
});

it('keeps the customer reminders switch when the data does not carry it', function () {
    updateSettings(['customer_reminders_enabled' => true]);
    updateSettings(['renewal_lead_days' => 20]);

    expect(app(Settings::class)->customerRemindersEnabled())->toBeTrue();
});

it('rejects a customer reminders switch that is not a boolean', function () {
    expect(fn () => updateSettings(['customer_reminders_enabled' => 'belki']))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('customer_reminders_enabled'));

    expect(app(Settings::class)->customerRemindersEnabled())->toBeFalse();
});
