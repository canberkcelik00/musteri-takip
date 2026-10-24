<?php

use App\Enums\DigestFrequency;
use App\Enums\RefreshIntervalUnit;
use App\Enums\SettingKey;
use App\Livewire\Pages\Settings\General;
use App\Mail\TestMail;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('local');
});

it('opens with the stored settings', function () {
    $settings = app(Settings::class);
    [$refreshValue, $refreshUnit] = RefreshIntervalUnit::split($settings->usageRefreshMinutes());

    Livewire::test(General::class)
        ->assertSet('renewal_lead_days', (string) $settings->renewalLeadDays())
        ->assertSet('digest_frequency', $settings->digestFrequency()->value)
        ->assertSet('usage_refresh_value', (string) $refreshValue)
        ->assertSet('usage_refresh_unit', $refreshUnit->value);
});

it('saves the settings and shows a toast', function () {
    Livewire::test(General::class)
        ->set('renewal_lead_days', '45')
        ->set('digest_frequency', DigestFrequency::Monthly->value)
        ->set('digest_day_of_month', '5')
        ->set('digest_time', '09:30')
        ->set('notification_email', 'sahip@example.com')
        ->set('usage_warning_percent', '85')
        ->set('usage_refresh_value', '2')
        ->set('usage_refresh_unit', 'hour')
        ->set('customer_reminders_enabled', true)
        ->set('quote_validity_days', '10')
        ->set('quote_bcc_email', 'kopya@example.com')
        ->set('company_name', 'Logi Bilişim')
        ->set('company_iban', 'tr33 0006 1005 1978 6457 8413 26')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('notify', message: __('settings.saved'))
        ->assertSet('company_iban', 'TR330006100519786457841326');

    $settings = app(Settings::class);

    expect($settings->renewalLeadDays())->toBe(45)
        ->and($settings->digestFrequency())->toBe(DigestFrequency::Monthly)
        ->and($settings->digestDayOfMonth())->toBe(5)
        ->and($settings->digestTime())->toBe('09:30')
        ->and($settings->notificationEmail())->toBe('sahip@example.com')
        ->and($settings->usageWarningPercent())->toBe(85)
        ->and($settings->usageRefreshMinutes())->toBe(120)
        ->and($settings->customerRemindersEnabled())->toBeTrue()
        ->and($settings->quoteValidityDays())->toBe(10)
        ->and($settings->quoteBccEmail())->toBe('kopya@example.com')
        ->and($settings->companyDetails()['name'])->toBe('Logi Bilişim')
        ->and($settings->companyDetails()['iban'])->toBe('TR330006100519786457841326');
});

it('shows a validation error under the field and stores nothing', function () {
    $before = app(Settings::class)->renewalLeadDays();

    Livewire::test(General::class)
        ->set('renewal_lead_days', '0')
        ->set('notification_email', 'bozuk')
        ->set('company_iban', 'TR12')
        ->call('save')
        ->assertHasErrors(['renewal_lead_days', 'notification_email', 'company_iban']);

    expect(app(Settings::class)->renewalLeadDays())->toBe($before);
});

it('rejects a refresh interval under 15 minutes', function () {
    Livewire::test(General::class)
        ->set('usage_refresh_value', '5')
        ->set('usage_refresh_unit', 'minute')
        ->call('save')
        ->assertHasErrors('usage_refresh_value');
});

it('uploads a logo, replaces it and removes it', function () {
    $component = Livewire::test(General::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->call('save')
        ->assertHasNoErrors();

    $first = app(Settings::class)->companyDetails()['logo_path'];
    expect($first)->toStartWith('company/');
    Storage::disk('local')->assertExists($first);

    $component->set('logo', UploadedFile::fake()->image('new.png', 100, 100))->call('save')->assertHasNoErrors();

    $second = app(Settings::class)->companyDetails()['logo_path'];
    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $component->set('remove_logo', true)->call('save')->assertHasNoErrors();

    expect(app(Settings::class)->companyDetails()['logo_path'])->toBeNull();
    Storage::disk('local')->assertMissing($second);
});

it('rejects a logo that is not an image or is too large', function () {
    Livewire::test(General::class)
        ->set('logo', UploadedFile::fake()->create('belge.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('logo');

    Livewire::test(General::class)
        ->set('logo', UploadedFile::fake()->image('buyuk.png')->size(2048))
        ->call('save')
        ->assertHasErrors('logo');
});

it('sends the test e-mail to the saved address', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    Livewire::test(General::class)
        ->call('sendTestEmail')
        ->assertDispatched('notify', fn (string $event, array $params): bool => str_contains($params['message'], 'sahip@example.com'));

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('sahip@example.com'));
});

it('warns when no notification address is saved', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, null);

    Livewire::test(General::class)
        ->call('sendTestEmail')
        ->assertDispatched('notify', message: __('settings.test_email_no_address'), tone: 'danger');

    Mail::assertNothingSent();
});

it('needs a signed-in user for the settings page', function () {
    auth()->logout();

    $this->get(route('app.settings'))->assertRedirect();
});
