<?php

namespace App\Actions\Servers;

use App\Enums\RemoteServerErrorReason;
use App\Enums\UsageLevel;
use Illuminate\Support\Carbon;

/**
 * What the "Kullanım" window shows for one linked hosting item (docs/UI.md "Kullanım penceresi"):
 * the main gauges, the counters, the mailboxes and the databases of its account, when they were
 * read and whether the last read failed. `sharedDomains` is the number of domains that share the
 * account on a cPanel connection (0 on WHM, where an account belongs to one item).
 */
final readonly class UsageReport
{
    /**
     * @param  list<UsageMeter>  $gauges
     * @param  list<UsageMeter>  $counters
     * @param  list<UsageMailbox>  $mailboxes
     * @param  list<UsageMeter>  $databases
     */
    public function __construct(
        public int $itemId,
        public string $title,
        public string $serverName,
        public string $username,
        public int $sharedDomains,
        public ?Carbon $refreshedAt,
        public ?Carbon $failedAt,
        public ?RemoteServerErrorReason $failureReason,
        public array $gauges,
        public array $counters,
        public array $mailboxes,
        public array $databases,
    ) {}

    public function hasData(): bool
    {
        return $this->refreshedAt !== null;
    }

    /**
     * The values at or over the threshold: the resources, then the mailboxes as one group
     * ("2 e-posta dolu"), the worst first.
     *
     * @return list<array{text: string, level: UsageLevel, rank: array{int, int}}>
     */
    public function problems(): array
    {
        $problems = [];

        foreach ([...$this->gauges, ...$this->counters] as $meter) {
            if ($meter->level !== null) {
                $problems[] = [
                    'text' => __('usage.badge_resource', ['resource' => $meter->label, 'percent' => $meter->percent()]),
                    'level' => $meter->level,
                    'rank' => [$meter->level->severity(), (int) $meter->percent()],
                ];
            }
        }

        $mailboxes = array_values(array_filter($this->mailboxes, fn (UsageMailbox $mailbox): bool => $mailbox->meter->level !== null));

        if ($mailboxes !== []) {
            $worst = collect($mailboxes)->contains(fn (UsageMailbox $mailbox): bool => $mailbox->meter->level === UsageLevel::Full)
                ? UsageLevel::Full
                : UsageLevel::NearlyFull;
            $problems[] = [
                'text' => __('usage.badge_mailboxes', ['count' => count($mailboxes), 'level' => mb_strtolower($worst->getLabel())]),
                'level' => $worst,
                'rank' => [$worst->severity(), (int) max(array_map(fn (UsageMailbox $mailbox): int => (int) $mailbox->meter->percent(), $mailboxes))],
            ];
        }

        usort($problems, fn (array $a, array $b): int => $b['rank'] <=> $a['rank']);

        return $problems;
    }

    /**
     * The one badge of the customer card's service row: the worst problem, plus how many more.
     *
     * @return array{text: string, level: UsageLevel}|null
     */
    public function badge(): ?array
    {
        $problems = $this->problems();

        if ($problems === []) {
            return null;
        }

        $more = count($problems) - 1;

        return [
            'text' => $problems[0]['text'].($more > 0 ? ' · '.__('usage.badge_more', ['count' => $more]) : ''),
            'level' => $problems[0]['level'],
        ];
    }

    /**
     * The one line of the edit window: "Disk 1,2 / 5 GB · 4 e-posta · 14:30".
     */
    public function summary(): string
    {
        $parts = [];

        foreach ($this->gauges as $meter) {
            if ($meter->id === 'disk_usage') {
                $parts[] = $meter->label.' '.$meter->usageText();
            }
        }

        if ($this->mailboxes !== []) {
            $parts[] = __('usage.summary_mailboxes', ['count' => count($this->mailboxes)]);
        }

        if ($this->refreshedAt !== null) {
            $parts[] = $this->refreshedAt->format('H:i');
        }

        return implode(' · ', $parts);
    }
}
