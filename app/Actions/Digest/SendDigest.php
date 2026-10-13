<?php

namespace App\Actions\Digest;

use App\Enums\DigestResult;
use App\Mail\DigestMail;
use App\Support\DigestSchedule;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Send the digest e-mail once per scheduled moment ("henüz yapılmadıysa yap"). Called on every
 * scheduler run; the digest of the latest scheduled moment is handled on the first run after it,
 * however many runs were missed in between.
 *
 * The run takes one moment, $now, and uses it for the schedule, the digest's time window and
 * the recorded run. The digest lists the charges created since the previous run that handled it,
 * so nothing created between two digests is left out. An empty digest is not sent, but its
 * period is closed. A failed send records nothing, so the next run tries again. The first run
 * ever only records its moment: the first digest goes at the next scheduled moment.
 */
class SendDigest
{
    public function __construct(
        private readonly Settings $settings,
        private readonly DigestSchedule $schedule,
        private readonly BuildDigest $buildDigest,
    ) {}

    /**
     * @return array{result: DigestResult, next_scheduled_at: Carbon}
     */
    public function handle(): array
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();

        return [
            'result' => $this->run($now),
            'next_scheduled_at' => $this->schedule->nextScheduledAt($now),
        ];
    }

    private function run(Carbon $now): DigestResult
    {
        $recipient = $this->settings->notificationEmail();

        if ($recipient === null) {
            return DigestResult::NoRecipient;
        }

        $lastRunAt = $this->settings->digestLastRunAt();

        if ($lastRunAt === null) {
            $this->settings->markDigestRun($now);

            return DigestResult::Started;
        }

        if ($lastRunAt->greaterThanOrEqualTo($this->schedule->lastScheduledAt($now))) {
            return DigestResult::NotDue;
        }

        $digest = $this->buildDigest->handle($lastRunAt, $now);

        if (BuildDigest::isEmpty($digest)) {
            $this->settings->markDigestRun($now);

            return DigestResult::Empty;
        }

        Mail::to($recipient)->send(new DigestMail($digest, $this->settings->digestFrequency(), $now));

        $this->settings->markDigestRun($now);

        return DigestResult::Sent;
    }
}
