<?php

namespace App\Actions\Servers;

use App\Mail\QuotaWarningMail;
use App\Models\AccountUsage;
use App\Models\Mailbox;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail the hosting resources that newly reached the warning threshold ("henüz yapılmadıysa
 * yap"; docs/DECISIONS.md T-115), one e-mail for all of them, to the notification address. Called
 * after every refresh run.
 *
 * What was announced is remembered per resource and per mailbox (`warned_levels`,
 * `warned_level`), so the same excess never sends a second e-mail; a worse level (nearly full,
 * then full) is announced again. When a resource falls back under the threshold its mark is
 * removed, and a later excess is announced as new. A failed send marks nothing, so the next run
 * tries again. Without a notification address nothing is sent and nothing is marked.
 */
class SendQuotaWarnings
{
    public function __construct(
        private readonly ListUsageAlerts $listUsageAlerts,
        private readonly Settings $settings,
    ) {}

    /**
     * @return int the number of alerts e-mailed
     */
    public function handle(): int
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();
        $alerts = $this->listUsageAlerts->handle();
        $recipient = $this->settings->notificationEmail();

        $toSend = $recipient === null ? collect() : $alerts->filter(fn (UsageAlert $alert): bool => $alert->needsWarning())->values();

        if ($toSend->isNotEmpty()) {
            Mail::to($recipient)->send(new QuotaWarningMail($toSend, $now, $this->settings->usageWarningPercent()));
        }

        $this->recordWarned($alerts, $toSend, $now);

        return $toSend->count();
    }

    /**
     * @param  Collection<int, UsageAlert>  $alerts
     * @param  Collection<int, UsageAlert>  $sent
     */
    private function recordWarned(Collection $alerts, Collection $sent, Carbon $now): void
    {
        $sentKeys = $sent->map(fn (UsageAlert $alert): string => self::keyOf($alert))->flip();

        foreach (AccountUsage::query()->get() as $usage) {
            $before = $usage->warned_levels ?? [];
            $after = [];
            $anySent = false;

            foreach ($alerts->where('accountUsageId', $usage->id)->whereNotNull('resourceId') as $alert) {
                $wasSent = $sentKeys->has(self::keyOf($alert));
                $anySent = $anySent || $wasSent;

                if ($wasSent || isset($before[$alert->resourceId])) {
                    $after[$alert->resourceId] = $alert->level->value;
                }
            }

            if ($after !== $before || $anySent) {
                $usage->update([
                    'warned_levels' => $after === [] ? null : $after,
                    'warning_sent_at' => $anySent ? $now : $usage->warning_sent_at,
                ]);
            }
        }

        foreach (Mailbox::query()->get() as $mailbox) {
            $alert = $alerts->firstWhere('mailboxId', $mailbox->id);
            $wasSent = $alert !== null && $sentKeys->has(self::keyOf($alert));
            $after = $alert !== null && ($wasSent || $mailbox->warned_level !== null) ? $alert->level : null;

            if ($after !== $mailbox->warned_level || $wasSent) {
                $mailbox->update([
                    'warned_level' => $after,
                    'warning_sent_at' => $wasSent ? $now : $mailbox->warning_sent_at,
                ]);
            }
        }
    }

    private static function keyOf(UsageAlert $alert): string
    {
        return $alert->mailboxId !== null ? "mailbox:{$alert->mailboxId}" : "resource:{$alert->accountUsageId}:{$alert->resourceId}";
    }
}
