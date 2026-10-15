<?php

use App\Actions\Reminders\SendRenewalReminder;
use App\Enums\CustomerServiceStatus;
use App\Enums\SettingKey;
use App\Exceptions\ReminderNotSent;
use App\Mail\RenewalReminderMail;
use App\Models\Charge;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();
    $this->travelTo('2027-02-10 10:00:00');
});

it('sends the reminder and records the time', function () {
    $charge = renewalChargeToRemind();

    app(SendRenewalReminder::class)->handle($charge);

    Mail::assertSent(RenewalReminderMail::class, 1);
    Mail::assertSent(RenewalReminderMail::class, fn (RenewalReminderMail $mail): bool => $mail->hasTo('ayse@example.com') && $mail->charge->is($charge));
    expect($charge->fresh()->reminder_sent_at?->toDateTimeString())->toBe('2027-02-10 10:00:00');
});

it('sends again and updates the record when the charge was reminded before', function () {
    $charge = renewalChargeToRemind(['reminder_sent_at' => '2027-02-01 09:00:00']);

    app(SendRenewalReminder::class)->handle($charge);

    Mail::assertSent(RenewalReminderMail::class, 1);
    expect($charge->fresh()->reminder_sent_at?->toDateTimeString())->toBe('2027-02-10 10:00:00');
});

it('sends while the automatic reminders setting is off', function () {
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, null);
    $charge = renewalChargeToRemind();

    app(SendRenewalReminder::class)->handle($charge);

    Mail::assertSent(RenewalReminderMail::class, 1);
    expect($charge->fresh()->reminder_sent_at)->not->toBeNull();
});

it('sends for an overdue renewal charge', function () {
    $charge = renewalChargeToRemind(['due_date' => '2027-02-01']);

    app(SendRenewalReminder::class)->handle($charge);

    Mail::assertSent(RenewalReminderMail::class, 1);
});

it('refuses a charge that is not an open renewal of an active paid service of an active customer', function (Closure $make) {
    $charge = $make();

    expect(app(SendRenewalReminder::class)->isEligible($charge))->toBeFalse()
        ->and(fn () => app(SendRenewalReminder::class)->handle($charge))->toThrow(ReminderNotSent::class);

    Mail::assertNothingSent();
    expect($charge->fresh()->reminder_sent_at)->toBeNull();
})->with([
    'manual charge' => [fn () => Charge::factory()->create(['due_date' => '2027-03-01'])],
    'paid charge' => [fn () => renewalChargeToRemind(['status' => 'paid'])],
    'cancelled charge' => [fn () => renewalChargeToRemind(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'İptal'])],
    'free service' => [fn () => renewalChargeToRemind(service: ['is_free' => true])],
    'cancelled service' => [fn () => renewalChargeToRemind(service: ['status' => CustomerServiceStatus::Cancelled, 'cancelled_on' => '2027-02-01'])],
    'archived customer' => [function () {
        $charge = renewalChargeToRemind();
        $charge->customer->forceFill(['archived_at' => now()])->save();

        return $charge->fresh();
    }],
]);

it('refuses a charge without a valid primary address and records nothing', function (?string $email) {
    $charge = renewalChargeToRemind(contact: ['email' => $email]);

    expect(fn () => app(SendRenewalReminder::class)->handle($charge))
        ->toThrow(ReminderNotSent::class, __('reminders.no_recipient'));

    Mail::assertNothingSent();
    expect($charge->fresh()->reminder_sent_at)->toBeNull();
})->with([
    'no address' => [null],
    'invalid address' => ['gecersiz'],
]);

it('records nothing when the send fails', function () {
    $charge = renewalChargeToRemind();
    Mail::shouldReceive('to')->once()->andReturn(new class
    {
        public function send(mixed $mailable): void
        {
            throw new TransportException('SMTP bağlantısı kurulamadı.');
        }
    });

    expect(fn () => app(SendRenewalReminder::class)->handle($charge))->toThrow(TransportException::class);
    expect($charge->fresh()->reminder_sent_at)->toBeNull();
});
