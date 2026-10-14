<?php

namespace App\Actions\Settings;

use App\Mail\TestMail;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Send a test e-mail to the saved notification address, with the same mail settings the digest
 * uses. Sent synchronously: there is no queue worker on the hosting. The saved address is used
 * on purpose, so the test proves the address the digest will actually go to.
 */
class SendTestEmail
{
    /** Length of the shown message including the "..." that Str::limit appends. */
    private const MESSAGE_LIMIT = 150;

    public function __construct(private readonly Settings $settings) {}

    /**
     * @return string|null the address the e-mail was sent to, or null when none is saved
     */
    public function handle(): ?string
    {
        $recipient = $this->settings->notificationEmail();

        if ($recipient === null) {
            return null;
        }

        Mail::to($recipient)->send(new TestMail);

        return $recipient;
    }

    /**
     * A short failure message that is safe to show on screen: the mailer's user name, password and
     * URL are masked (empty values are skipped) and the text is shortened. The full details belong
     * in the log only.
     */
    public function describeFailure(Throwable $exception): string
    {
        $mailer = (array) config('mail.mailers.'.config('mail.default'), []);
        $secrets = collect([$mailer['password'] ?? null, $mailer['username'] ?? null, $mailer['url'] ?? null])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->all();

        return Str::limit(str_replace($secrets, '***', $exception->getMessage()), self::MESSAGE_LIMIT - 3);
    }
}
