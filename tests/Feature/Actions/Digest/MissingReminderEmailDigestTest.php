<?php

use App\Actions\Digest\BuildDigest;
use App\Actions\Digest\SendDigest;
use App\Enums\DigestFrequency;
use App\Enums\DigestResult;
use App\Enums\SettingKey;
use App\Filament\Resources\Customers\CustomerResource;
use App\Mail\DigestMail;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::RenewalLeadDays, '30');
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '1');
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
    $this->travelTo('2027-02-10 10:00:00');
});

/**
 * @return list<int>
 */
function missingReminderEmailChargeIds(): array
{
    $now = Carbon::now('Europe/Istanbul');

    return app(BuildDigest::class)->handle($now->copy()->subMonth(), $now)['missing_reminder_email']->pluck('id')->all();
}

it('lists the waiting renewal charges whose primary contact has no valid address', function (?string $email) {
    $charge = renewalChargeToRemind(contact: ['email' => $email]);
    renewalChargeToRemind();

    expect(missingReminderEmailChargeIds())->toBe([$charge->id]);
})->with([
    'no address' => [null],
    'blank address' => ['  '],
    'invalid address' => ['gecersiz'],
]);

it('lists a customer without any contact', function () {
    $charge = renewalChargeToRemind();
    $charge->customer->contacts()->delete();

    expect(missingReminderEmailChargeIds())->toBe([$charge->id]);
});

it('leaves out charges that do not wait for a reminder', function () {
    renewalChargeToRemind(['reminder_sent_at' => now()], ['email' => null]);
    renewalChargeToRemind(service: ['is_free' => true], contact: ['email' => null]);
    renewalChargeToRemind(['status' => 'paid'], ['email' => null]);

    expect(missingReminderEmailChargeIds())->toBe([]);
});

it('is empty while the reminders are off', function () {
    renewalChargeToRemind(contact: ['email' => null]);
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '0');

    expect(missingReminderEmailChargeIds())->toBe([]);
});

it('goes empty once the address is fixed', function () {
    $charge = renewalChargeToRemind(contact: ['email' => 'gecersiz']);

    expect(missingReminderEmailChargeIds())->toBe([$charge->id]);

    $charge->customer->primaryContact->update(['email' => 'duzgun@example.com']);

    expect(missingReminderEmailChargeIds())->toBe([]);
});

it('sends the digest when the missing address is the only thing to report', function () {
    $charge = renewalChargeToRemind(contact: ['email' => null]);
    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Daily->value);
    app(Settings::class)->markDigestRun(Carbon::parse('2027-02-09 09:30:00', 'Europe/Istanbul'));
    // The renewal charge was created before the window of this digest.
    $charge->forceFill(['created_at' => '2027-02-01 10:00:00'])->save();
    $this->travelTo('2027-02-10 09:30:00');

    $result = app(SendDigest::class)->handle()['result'];

    expect($result)->toBe(DigestResult::Sent);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->digest['new_charges']->isEmpty()
        && $mail->digest['missing_reminder_email']->pluck('id')->all() === [$charge->id]);
});

it('shows the warning section in the mail, with a link to the customer card', function () {
    $charge = renewalChargeToRemind(contact: ['email' => null]);
    $now = Carbon::now('Europe/Istanbul');
    $mail = new DigestMail(app(BuildDigest::class)->handle($now->copy()->subMonth(), $now), DigestFrequency::Monthly, $now);
    $url = CustomerResource::getUrl('view', ['record' => $charge->customer_id], panel: 'panel');

    $mail->assertSeeInOrderInHtml([
        '1 hatırlatma gönderilemedi',
        'Hatırlatma gönderilemedi: birincil kişinin geçerli e-postası yok (1)',
        'Müşteri', 'Hizmet', 'Vade',
        $charge->customer->short_name, 'Kurumsal Paket', '01.03.2027',
    ]);
    $mail->assertSeeInHtml('href="'.$url.'"', false);
    $mail->assertSeeInOrderInText([
        'Hatırlatma gönderilemedi: birincil kişinin geçerli e-postası yok (1)',
        $charge->customer->short_name.' | Kurumsal Paket | Vade 01.03.2027',
        $url,
    ]);
});

it('does not show the warning section when there is nothing to warn about', function () {
    renewalChargeToRemind();
    $now = Carbon::now('Europe/Istanbul');
    $mail = new DigestMail(app(BuildDigest::class)->handle($now->copy()->subMonth(), $now), DigestFrequency::Monthly, $now);

    $mail->assertDontSeeInHtml('Hatırlatma gönderilemedi');
    $mail->assertDontSeeInText('Hatırlatma gönderilemedi');
});
