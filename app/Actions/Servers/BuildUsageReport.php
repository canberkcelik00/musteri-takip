<?php

namespace App\Actions\Servers;

use App\Enums\ServerType;
use App\Enums\UsageLevel;
use App\Models\AccountUsage;
use App\Models\CustomerServiceItem;
use App\Models\Mailbox;
use App\Models\Server;
use App\Support\Servers\LinkedAccounts;
use App\Support\Servers\ResourceLabel;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Reads the stored usage of the account a linked item belongs to and prepares it for the
 * "Kullanım" window, the card's badge and the edit window's summary line (docs/UI.md "Kullanım
 * penceresi"). Reads only what the refresh stored; never calls the server. Levels come from the
 * same threshold and the same rules as the warnings ({@see ListUsageAlerts}).
 */
class BuildUsageReport
{
    /**
     * The three resources shown as gauges, in this order; every other resource is a counter.
     */
    private const array GAUGE_IDS = ['disk_usage', 'bandwidth', 'file_usage'];

    public function __construct(private readonly Settings $settings) {}

    public function handle(CustomerServiceItem $item): ?UsageReport
    {
        return $this->handleMany(new Collection([$item]))[$item->getKey()] ?? null;
    }

    /**
     * The reports of several items with the stored usage, the mailboxes and the shared-domain counts read once for
     * all of them (a customer card lists many hosting items; one query per item made it slow).
     *
     * @param  Collection<int, CustomerServiceItem>  $items  with `server` loaded
     * @return array<int, UsageReport> keyed by item id; an item without a linked account has none
     */
    public function handleMany(Collection $items): array
    {
        $linked = $items->filter(fn (CustomerServiceItem $item): bool => $item->server instanceof Server && $item->remote_key !== null);

        if ($linked->isEmpty()) {
            return [];
        }

        $serverIds = $linked->pluck('server_id')->unique()->all();
        $usernames = $linked->map(fn (CustomerServiceItem $item): string => LinkedAccounts::usernameOf($item))->unique()->all();
        $keyOf = fn (AccountUsage|Mailbox $row): string => LinkedAccounts::keyOf($row->server_id, $row->remote_username);

        $usages = AccountUsage::query()->whereIn('server_id', $serverIds)->whereIn('remote_username', $usernames)->get()->keyBy($keyOf);
        $mailboxes = Mailbox::query()->whereIn('server_id', $serverIds)->whereIn('remote_username', $usernames)->get()->groupBy($keyOf);
        $shared = $linked->contains(fn (CustomerServiceItem $item): bool => $item->server?->type === ServerType::Cpanel) ? $this->sharedDomainCounts() : [];
        $threshold = $this->settings->usageWarningPercent();
        $reports = [];

        foreach ($linked as $item) {
            $server = $item->server;
            assert($server instanceof Server);
            $username = LinkedAccounts::usernameOf($item);
            $key = LinkedAccounts::keyOf($server->getKey(), $username);

            $reports[$item->getKey()] = $this->build($item, $server, $username, $usages->get($key), $mailboxes->get($key, new Collection), $shared[$key] ?? 0, $threshold);
        }

        return $reports;
    }

    /**
     * @param  Collection<int, Mailbox>  $accountMailboxes  every mailbox of the item's account
     */
    private function build(CustomerServiceItem $item, Server $server, string $username, ?AccountUsage $usage, Collection $accountMailboxes, int $sharedDomains, int $threshold): UsageReport
    {
        $isCpanel = $server->type === ServerType::Cpanel;
        $domain = (string) ($item->details['domain'] ?? $item->remote_key);

        $meters = collect($usage->resources ?? [])->map(fn (array $resource): UsageMeter => new UsageMeter(
            id: $resource['id'],
            label: ResourceLabel::for($resource['id'], $resource['label']),
            used: $resource['used'],
            limit: $resource['limit'],
            isBytes: $resource['is_bytes'],
            level: ListUsageAlerts::isMomentary($resource['id']) ? null : UsageLevel::of($resource['used'], $resource['limit'], $threshold),
        ));

        $gauges = collect(self::GAUGE_IDS)
            ->map(fn (string $id): ?UsageMeter => $meters->firstWhere('id', $id))
            ->filter()
            ->values();

        return new UsageReport(
            itemId: $item->getKey(),
            title: $domain,
            serverName: $server->name,
            username: $username,
            sharedDomains: $isCpanel ? $sharedDomains : 0,
            refreshedAt: $usage?->refreshed_at,
            failedAt: $usage?->failed_at,
            failureReason: $usage?->failure_reason,
            gauges: $gauges->all(),
            counters: $meters->reject(fn (UsageMeter $meter): bool => in_array($meter->id, self::GAUGE_IDS, true))->values()->all(),
            mailboxes: $this->mailboxes($accountMailboxes, $isCpanel ? (string) $item->remote_key : null, $threshold),
            databases: collect($usage->databases ?? [])
                ->map(fn (array $database): UsageMeter => new UsageMeter($database['name'], $database['name'], $database['bytes'], null, true, null))
                ->all(),
            domains: $item->otherDomains(),
        );
    }

    /**
     * Domains of the cPanel connection's linked, active services that share each account, keyed by account.
     *
     * @return array<string, int>
     */
    private function sharedDomainCounts(): array
    {
        return LinkedAccounts::items()
            ->groupBy(fn (CustomerServiceItem $item): string => LinkedAccounts::keyOf($item->server_id, LinkedAccounts::usernameOf($item)))
            ->map(fn ($items): int => $items->map(fn (CustomerServiceItem $item): string => (string) $item->remote_key)->unique()->count())
            ->all();
    }

    /**
     * The account's mailboxes, fullest first (those without a quota last); on a cPanel
     * connection only those of the item's own domain.
     *
     * @param  Collection<int, Mailbox>  $accountMailboxes
     * @return list<UsageMailbox>
     */
    private function mailboxes(Collection $accountMailboxes, ?string $domain, int $threshold): array
    {
        return $accountMailboxes
            ->when($domain !== null, fn (Collection $mailboxes): Collection => $mailboxes->where('domain', $domain))
            ->map(fn (Mailbox $mailbox): UsageMailbox => new UsageMailbox(
                $mailbox->address,
                new UsageMeter($mailbox->address, $mailbox->address, $mailbox->used_bytes, $mailbox->quota_bytes, true, UsageLevel::of($mailbox->used_bytes, $mailbox->quota_bytes, $threshold)),
                $mailbox->login_suspended,
                $mailbox->incoming_suspended,
            ))
            ->sort(fn (UsageMailbox $a, UsageMailbox $b): int => [$b->meter->percent() ?? -1, $a->address] <=> [$a->meter->percent() ?? -1, $b->address])
            ->values()
            ->all();
    }
}
