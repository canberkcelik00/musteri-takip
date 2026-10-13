<?php

use App\Actions\Digest\SendDigest;
use App\Enums\DigestFrequency;
use App\Enums\DigestResult;
use App\Enums\SettingKey;
use App\Mail\DigestMail;
use App\Models\Charge;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();
});

function digestSettings(DigestFrequency $frequency = DigestFrequency::Monthly, int $day = 15, ?string $email = 'sahip@example.com'): void
{
    $settings = app(Settings::class);
    $settings->set(SettingKey::DigestFrequency, $frequency->value);
    $settings->set(SettingKey::DigestDayOfMonth, (string) $day);
    $settings->set(SettingKey::DigestTime, '09:00');
    $settings->set(SettingKey::NotificationEmail, $email);
}

/**
 * Run the digest at the given moment (Istanbul time) and return what it did.
 */
function runDigestAt(string $moment): DigestResult
{
    test()->travelTo($moment);

    return app(SendDigest::class)->handle()['result'];
}

/**
 * Start the digest tracking at the given moment (the first run only records it).
 */
function startDigestAt(string $moment): void
{
    expect(runDigestAt($moment))->toBe(DigestResult::Started);
}

/**
 * An overdue charge created at the current (travelled) moment, so it is not a new charge of a
 * digest window that starts at this moment.
 */
function overdueCharge(): Charge
{
    return Charge::factory()->create(['due_date' => '2026-01-01']);
}

function lastDigestRun(): ?string
{
    return app(Settings::class)->digestLastRunAt()?->toDateTimeString();
}

/**
 * @return list<int>
 */
function sentNewChargeIds(int $index = -1): array
{
    $mails = Mail::sent(DigestMail::class)->values();
    $mail = $index < 0 ? $mails->last() : $mails[$index];

    return $mail->digest['new_charges']->pluck('id')->all();
}

it('only records the starting moment on the first run', function () {
    digestSettings();
    overdueCharge();

    startDigestAt('2026-10-13 10:00:00');

    Mail::assertNothingSent();
    expect(lastDigestRun())->toBe('2026-10-13 10:00:00');
});

it('sends nothing and records nothing without a notification address', function () {
    digestSettings(DigestFrequency::Daily, email: null);
    overdueCharge();

    expect(runDigestAt('2026-10-13 10:00:00'))->toBe(DigestResult::NoRecipient)
        ->and(runDigestAt('2026-10-14 10:00:00'))->toBe(DigestResult::NoRecipient)
        ->and(Setting::query()->where('key', SettingKey::DigestLastRunAt->value)->exists())->toBeFalse();

    Mail::assertNothingSent();
});

it('returns the next scheduled moment', function () {
    digestSettings();

    $this->travelTo('2026-10-13 10:00:00');
    $run = app(SendDigest::class)->handle();

    expect($run['result'])->toBe(DigestResult::Started)
        ->and($run['next_scheduled_at']->toDateTimeString())->toBe('2026-10-15 09:00:00');
});

describe('daily', function () {
    beforeEach(function () {
        digestSettings(DigestFrequency::Daily);
        startDigestAt('2026-10-12 12:00:00');
        overdueCharge();
    });

    it('is not sent before the time and is sent at the time', function () {
        expect(runDigestAt('2026-10-13 08:59:59'))->toBe(DigestResult::NotDue);
        Mail::assertNothingSent();

        expect(runDigestAt('2026-10-13 09:00:00'))->toBe(DigestResult::Sent);
        Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo('sahip@example.com'));
    });

    it('is sent once a day and again the next day', function () {
        runDigestAt('2026-10-13 09:00:00');

        expect(runDigestAt('2026-10-13 09:15:00'))->toBe(DigestResult::NotDue)
            ->and(runDigestAt('2026-10-13 23:45:00'))->toBe(DigestResult::NotDue);
        Mail::assertSentCount(1);

        expect(runDigestAt('2026-10-14 09:00:00'))->toBe(DigestResult::Sent);
        Mail::assertSentCount(2);
    });

    it('is sent once on the first run after missed runs, with the charges created meanwhile', function () {
        $this->travelTo('2026-10-13 15:00:00');
        $first = Charge::factory()->create(['due_date' => '2026-12-01']);
        $this->travelTo('2026-10-14 15:00:00');
        $second = Charge::factory()->create(['due_date' => '2026-12-01']);

        expect(runDigestAt('2026-10-15 13:00:00'))->toBe(DigestResult::Sent)
            ->and(runDigestAt('2026-10-15 13:15:00'))->toBe(DigestResult::NotDue);

        Mail::assertSentCount(1);
        expect(sentNewChargeIds())->toBe([$first->id, $second->id])
            ->and(lastDigestRun())->toBe('2026-10-15 13:00:00');
    });
});

describe('monthly on the 15th', function () {
    beforeEach(function () {
        digestSettings(DigestFrequency::Monthly, 15);
        startDigestAt('2026-09-20 10:00:00');
        overdueCharge();
    });

    it('is sent on the 15th from the time on, once a month', function () {
        expect(runDigestAt('2026-10-14 09:00:00'))->toBe(DigestResult::NotDue)
            ->and(runDigestAt('2026-10-15 08:59:00'))->toBe(DigestResult::NotDue);
        Mail::assertNothingSent();

        expect(runDigestAt('2026-10-15 09:00:00'))->toBe(DigestResult::Sent)
            ->and(runDigestAt('2026-10-15 09:15:00'))->toBe(DigestResult::NotDue)
            ->and(runDigestAt('2026-10-31 09:00:00'))->toBe(DigestResult::NotDue);
        Mail::assertSentCount(1);

        expect(runDigestAt('2026-11-15 09:00:00'))->toBe(DigestResult::Sent);
        Mail::assertSentCount(2);
    });

    it('is sent once on the first run after a missed 15th', function () {
        expect(runDigestAt('2026-10-17 10:00:00'))->toBe(DigestResult::Sent)
            ->and(runDigestAt('2026-10-17 10:15:00'))->toBe(DigestResult::NotDue);

        Mail::assertSentCount(1);
    });

    it('lists the charges created since the previous digest', function () {
        $this->travelTo('2026-09-21 10:00:00');
        $september = Charge::factory()->create(['due_date' => '2026-12-01']);
        $this->travelTo('2026-10-14 10:00:00');
        $october = Charge::factory()->create(['due_date' => '2026-12-01']);

        runDigestAt('2026-10-15 09:00:00');

        $this->travelTo('2026-11-01 10:00:00');
        $november = Charge::factory()->create(['due_date' => '2026-12-20']);

        runDigestAt('2026-11-15 09:00:00');

        expect(sentNewChargeIds(0))->toBe([$september->id, $october->id])
            ->and(sentNewChargeIds(1))->toBe([$november->id]);
    });

    it('puts a charge created at the run moment in this digest and one a second later in the next', function () {
        $this->travelTo('2026-10-15 09:00:00');
        $atRun = Charge::factory()->create(['due_date' => '2026-12-01']);
        runDigestAt('2026-10-15 09:00:00');

        $this->travelTo('2026-10-15 09:00:01');
        $afterRun = Charge::factory()->create(['due_date' => '2026-12-01']);
        runDigestAt('2026-11-15 09:00:00');

        expect(sentNewChargeIds(0))->toBe([$atRun->id])
            ->and(sentNewChargeIds(1))->toBe([$afterRun->id])
            ->and(lastDigestRun())->toBe('2026-11-15 09:00:00');
    });

    it('follows a day moved later in the month: sent again on the new day', function () {
        runDigestAt('2026-10-15 09:00:00');

        app(Settings::class)->set(SettingKey::DigestDayOfMonth, '20');

        expect(runDigestAt('2026-10-19 09:00:00'))->toBe(DigestResult::NotDue)
            ->and(runDigestAt('2026-10-20 09:00:00'))->toBe(DigestResult::Sent)
            ->and(runDigestAt('2026-10-20 09:15:00'))->toBe(DigestResult::NotDue);
        Mail::assertSentCount(2);
    });

    it('follows a day moved earlier in the month: the next digest is on that day next month', function () {
        runDigestAt('2026-10-15 09:00:00');

        app(Settings::class)->set(SettingKey::DigestDayOfMonth, '10');

        $this->travelTo('2026-10-20 09:00:00');
        $run = app(SendDigest::class)->handle();

        expect($run['result'])->toBe(DigestResult::NotDue)
            ->and($run['next_scheduled_at']->toDateTimeString())->toBe('2026-11-10 09:00:00')
            ->and(runDigestAt('2026-11-09 09:00:00'))->toBe(DigestResult::NotDue)
            ->and(runDigestAt('2026-11-10 09:00:00'))->toBe(DigestResult::Sent);
        Mail::assertSentCount(2);
    });
});

it('closes an empty period without sending; a charge created later is in the next digest', function () {
    digestSettings(DigestFrequency::Monthly, 15);
    startDigestAt('2026-09-20 10:00:00');

    expect(runDigestAt('2026-10-15 09:00:00'))->toBe(DigestResult::Empty)
        ->and(lastDigestRun())->toBe('2026-10-15 09:00:00');

    $this->travelTo('2026-10-15 14:00:00');
    $charge = Charge::factory()->create(['due_date' => '2026-12-01']);

    expect(runDigestAt('2026-10-15 14:15:00'))->toBe(DigestResult::NotDue);
    Mail::assertNothingSent();

    expect(runDigestAt('2026-11-15 09:00:00'))->toBe(DigestResult::Sent)
        ->and(sentNewChargeIds())->toBe([$charge->id]);
});

it('follows a switch from daily to monthly and back', function () {
    digestSettings(DigestFrequency::Daily);
    startDigestAt('2026-10-12 12:00:00');
    overdueCharge();
    runDigestAt('2026-10-13 09:00:00');

    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Monthly->value);

    expect(runDigestAt('2026-10-14 09:00:00'))->toBe(DigestResult::NotDue)
        ->and(runDigestAt('2026-10-15 09:00:00'))->toBe(DigestResult::Sent)
        ->and(runDigestAt('2026-10-16 09:00:00'))->toBe(DigestResult::NotDue);

    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Daily->value);

    expect(runDigestAt('2026-10-17 09:00:00'))->toBe(DigestResult::Sent);
    Mail::assertSentCount(3);
});

it('records nothing when sending fails, so the next run sends it', function () {
    digestSettings(DigestFrequency::Monthly, 15);
    startDigestAt('2026-09-20 10:00:00');
    overdueCharge();

    Mail::shouldReceive('to')->once()->andThrow(new TransportException('SMTP bağlantısı kurulamadı.'));
    $this->travelTo('2026-10-15 09:00:00');

    expect(fn () => app(SendDigest::class)->handle())->toThrow(TransportException::class)
        ->and(lastDigestRun())->toBe('2026-09-20 10:00:00');

    Mail::swap(new MailManager(app()));
    Mail::fake();

    expect(runDigestAt('2026-10-15 09:15:00'))->toBe(DigestResult::Sent)
        ->and(lastDigestRun())->toBe('2026-10-15 09:15:00');
    Mail::assertSentCount(1);
});
