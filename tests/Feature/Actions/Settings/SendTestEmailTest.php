<?php

use App\Actions\Settings\SendTestEmail;
use App\Enums\SettingKey;
use App\Mail\TestMail;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

it('sends one e-mail synchronously to the saved notification address', function () {
    Mail::fake();
    app(Settings::class)->set(SettingKey::NotificationEmail, 'sahip@example.com');

    expect(app(SendTestEmail::class)->handle())->toBe('sahip@example.com');

    Mail::assertSent(TestMail::class, 1);
    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('sahip@example.com'));
    Mail::assertNotQueued(TestMail::class);
});

it('is not a queued mailable, so it is always sent synchronously', function () {
    expect(new TestMail)->not->toBeInstanceOf(ShouldQueue::class);
});

it('sends nothing when no notification address is saved', function () {
    Mail::fake();

    expect(app(SendTestEmail::class)->handle())->toBeNull();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('masks the mailer user name and password in the failure message', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.username' => 'bildirim@alan.adi',
        'mail.mailers.smtp.password' => 'S3cret!pw',
    ]);

    $message = app(SendTestEmail::class)->describeFailure(
        new RuntimeException('Auth failed for bildirim@alan.adi with S3cret!pw'),
    );

    expect($message)->toBe('Auth failed for *** with ***');
});

it('leaves the message intact when the user name and password are empty', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => '',
        'mail.mailers.smtp.url' => null,
    ]);

    $message = app(SendTestEmail::class)->describeFailure(new RuntimeException('Connection refused'));

    expect($message)->toBe('Connection refused');
});

it('shortens a long failure message to about 150 characters', function () {
    $message = app(SendTestEmail::class)->describeFailure(new RuntimeException(str_repeat('a', 400)));

    expect(mb_strlen($message))->toBeLessThanOrEqual(150);
});
