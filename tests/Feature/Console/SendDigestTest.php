<?php

use App\Enums\DigestFrequency;
use App\Enums\SettingKey;
use App\Mail\DigestMail;
use App\Models\Charge;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');
});

it('runs on every scheduler call without overlapping, after the customer services', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->values();

    $digestIndex = $commands->search(fn (string $command): bool => str_contains($command, 'app:send-digest'));
    $servicesIndex = $commands->search(fn (string $command): bool => str_contains($command, 'app:process-customer-services'));
    $event = app(Schedule::class)->events()[$digestIndex];

    expect($digestIndex)->not->toBeFalse()
        ->and($digestIndex)->toBeGreaterThan($servicesIndex)
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(60);
});

it('starts the tracking and tells the next scheduled moment', function () {
    $this->travelTo('2026-10-13 10:00:00');

    $this->artisan('app:send-digest')
        ->expectsOutputToContain('Özet takibi başladı')
        ->expectsOutputToContain('Sıradaki özet: 15.10.2026 09:00')
        ->assertSuccessful();

    Mail::assertNothingSent();
});

it('sends the digest and tells the next scheduled moment', function () {
    app(Settings::class)->set(SettingKey::DigestFrequency, DigestFrequency::Monthly->value);
    $this->travelTo('2026-10-13 10:00:00');
    $this->artisan('app:send-digest')->assertSuccessful();
    Charge::factory()->create(['due_date' => '2026-09-01']);

    $this->travelTo('2026-10-15 09:00:00');

    $this->artisan('app:send-digest')
        ->expectsOutputToContain('Özet gönderildi.')
        ->expectsOutputToContain('Sıradaki özet: 15.11.2026 09:00')
        ->assertSuccessful();

    $this->artisan('app:send-digest')
        ->expectsOutputToContain('Bu dönemin özeti zaten ele alındı.')
        ->expectsOutputToContain('Sıradaki özet: 15.11.2026 09:00')
        ->assertSuccessful();

    Mail::assertSentCount(1);
    Mail::assertSent(DigestMail::class, fn (DigestMail $mail): bool => $mail->hasTo('sahip@example.com'));
});

it('says when no notification address is set', function () {
    app(Settings::class)->set(SettingKey::NotificationEmail, null);

    $this->artisan('app:send-digest')
        ->expectsOutputToContain('Bildirim adresi girilmemiş')
        ->assertSuccessful();
});
