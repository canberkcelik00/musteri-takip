<?php

use App\Actions\CustomerServices\CreateDueRenewalCharges;
use App\Enums\DigestFrequency;
use App\Enums\SettingKey;
use App\Filament\Pages\ManageSettings;
use App\Mail\TestMail;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
            'digest_frequency' => DigestFrequency::Monthly,
            'digest_day_of_month' => 15,
            'digest_time' => '09:00',
            'notification_email' => null,
            'customer_reminders_enabled' => false,
        ]);
});

it('saves the customer reminders switch and shows it as saved', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Müşteriye yenileme hatırlatması gönder')
        ->fillForm(['customer_reminders_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->customerRemindersEnabled())->toBeTrue();

    Livewire::test(ManageSettings::class)
        ->assertSchemaStateSet(['customer_reminders_enabled' => true])
        ->fillForm(['customer_reminders_enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->customerRemindersEnabled())->toBeFalse();
});

it('shows the day of the month only for the monthly digest', function () {
    Livewire::test(ManageSettings::class)
        ->assertFormFieldVisible('digest_day_of_month')
        ->assertSee('29–31 her ayda olmadığı için seçilemez.')
        ->fillForm(['digest_frequency' => DigestFrequency::Daily->value])
        ->assertFormFieldHidden('digest_day_of_month')
        ->fillForm(['digest_frequency' => DigestFrequency::Monthly->value])
        ->assertFormFieldVisible('digest_day_of_month');
});

it('saves the digest frequency and the day of the month', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['digest_frequency' => DigestFrequency::Monthly->value, 'digest_day_of_month' => 20])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->digestFrequency())->toBe(DigestFrequency::Monthly)
        ->and(app(Settings::class)->digestDayOfMonth())->toBe(20);

    Livewire::test(ManageSettings::class)
        ->fillForm(['digest_frequency' => DigestFrequency::Daily->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(Settings::class)->digestFrequency())->toBe(DigestFrequency::Daily);
});

it('requires a day of the month from 1 to 28 for the monthly digest', function (mixed $day) {
    Livewire::test(ManageSettings::class)
        ->fillForm(['digest_frequency' => DigestFrequency::Monthly->value, 'digest_day_of_month' => $day])
        ->call('save')
        ->assertHasFormErrors(['digest_day_of_month']);

    expect(app(Settings::class)->digestDayOfMonth())->toBe(15);
})->with([
    'no day' => [null],
    'zero' => [0],
    'day 29' => [29],
]);

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
        ->assertSee('Özet sıklığı')
        ->assertSee('Özet saati')
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

it('shows the test e-mail button', function () {
    Livewire::test(ManageSettings::class)
        ->assertSee('Test e-postası gönder');
});

it('sends the test e-mail to the saved address and says so', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    Livewire::test(ManageSettings::class)
        ->call('sendTestEmail')
        ->assertNotified('Test e-postası kayıtlı bildirim adresine (sahip@example.com) gönderildi.');

    Mail::assertSent(TestMail::class, 1);
});

it('uses the saved address and says so when the form holds an unsaved one', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    Livewire::test(ManageSettings::class)
        ->fillForm(['notification_email' => 'baska@example.com'])
        ->call('sendTestEmail')
        ->assertNotified(
            Notification::make()
                ->success()
                ->title('Test e-postası kayıtlı bildirim adresine (sahip@example.com) gönderildi.')
                ->body('Kayıtlı adres kullanıldı; formdaki değişiklik henüz kaydedilmedi.'),
        );

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('sahip@example.com') && ! $mail->hasTo('baska@example.com'));
});

it('warns and sends nothing when no address is saved', function () {
    Mail::fake();

    Livewire::test(ManageSettings::class)
        ->call('sendTestEmail')
        ->assertNotified('Önce bildirim adresini girip kaydedin.');

    Mail::assertNothingSent();
});

it('reports a send failure with a masked, shortened message and logs the full error', function () {
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.username' => 'bildirim@alan.adi',
        'mail.mailers.smtp.password' => 'S3cret!pw',
    ]);
    $failure = new RuntimeException('Auth failed for bildirim@alan.adi / S3cret!pw '.str_repeat('x', 300));
    Mail::shouldReceive('to')->once()->andThrow($failure);
    Exceptions::fake();

    $shown = Str::limit('Auth failed for *** / *** '.str_repeat('x', 300), 147);

    Livewire::test(ManageSettings::class)
        ->call('sendTestEmail')
        ->assertNotified('Test e-postası gönderilemedi: '.$shown);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception === $failure);

    expect($shown)->not->toContain('S3cret!pw')->not->toContain('bildirim@alan.adi')
        ->and(mb_strlen($shown))->toBe(150);
});
