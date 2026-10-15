<?php

use App\Enums\SettingKey;
use App\Mail\RenewalReminderMail;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '1');
    $this->travelTo('2027-02-10 10:00:00');
});

it('runs on every scheduler call without overlapping, between the customer services and the digest', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->values();

    $servicesIndex = $commands->search(fn (string $command): bool => str_contains($command, 'app:process-customer-services'));
    $remindersIndex = $commands->search(fn (string $command): bool => str_contains($command, 'app:send-renewal-reminders'));
    $digestIndex = $commands->search(fn (string $command): bool => str_contains($command, 'app:send-digest'));
    $event = app(Schedule::class)->events()[$remindersIndex];

    expect($remindersIndex)->not->toBeFalse()
        ->and($remindersIndex)->toBeGreaterThan($servicesIndex)
        ->and($remindersIndex)->toBeLessThan($digestIndex)
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60);
});

it('is skipped while a pulled release is not deployed yet', function () {
    fakePendingRelease(true);
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'app:send-renewal-reminders'));

    expect($event->filtersPass(app()))->toBeFalse();

    fakePendingRelease(false);

    expect($event->filtersPass(app()))->toBeTrue();
});

it('sends the reminders and tells how many', function () {
    renewalChargeToRemind();
    renewalChargeToRemind(contact: ['email' => 'gecersiz']);

    $this->artisan('app:send-renewal-reminders')
        ->expectsOutputToContain('1 hatırlatma gönderildi, 1 atlandı (geçerli e-posta yok), 0 gönderilemedi.')
        ->assertSuccessful();

    Mail::assertSent(RenewalReminderMail::class, 1);
});

it('sends nothing while the setting is off', function () {
    app(Settings::class)->set(SettingKey::CustomerRemindersEnabled, '0');
    renewalChargeToRemind();

    $this->artisan('app:send-renewal-reminders')
        ->expectsOutputToContain('0 hatırlatma gönderildi')
        ->assertSuccessful();

    Mail::assertNothingSent();
});
