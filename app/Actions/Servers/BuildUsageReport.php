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
        $server = $item->server;

        if (! $server instanceof Server || $item->remote_key === null) {
            return null;
        }

        $username = LinkedAccounts::usernameOf($item);
        $usage = AccountUsage::query()->where(['server_id' => $server->getKey(), 'remote_username' => $username])->first();
        $threshold = $this->settings->usageWarningPercent();
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
            sharedDomains: $isCpanel ? $this->sharedDomains($server, $username) : 0,
            refreshedAt: $usage?->refreshed_at,
            failedAt: $usage?->failed_at,
            failureReason: $usage?->failure_reason,
            gauges: $gauges->all(),
            counters: $meters->reject(fn (UsageMeter $meter): bool => in_array($meter->id, self::GAUGE_IDS, true))->values()->all(),
            mailboxes: $this->mailboxes($server, $username, $isCpanel ? (string) $item->remote_key : null, $threshold),
            databases: collect($usage->databases ?? [])
                ->map(fn (array $database): UsageMeter => new UsageMeter($database['name'], $database['name'], $database['bytes'], null, true, null))
                ->all(),
            domains: $item->otherDomains(),
        );
    }

    /**
     * Domains of the cPanel connection's linked, active services that share its account.
     */
    private function sharedDomains(Server $server, string $username): int
    {
        $key = LinkedAccounts::keyOf($server->getKey(), $username);

        return LinkedAccounts::items()
            ->filter(fn (CustomerServiceItem $item): bool => LinkedAccounts::keyOf($item->server_id, LinkedAccounts::usernameOf($item)) === $key)
            ->map(fn (CustomerServiceItem $item): string => (string) $item->remote_key)
            ->unique()
            ->count();
    }

    /**
     * The account's mailboxes, fullest first (those without a quota last); on a cPanel
     * connection only those of the item's own domain.
     *
     * @return list<UsageMailbox>
     */
    private function mailboxes(Server $server, string $username, ?string $domain, int $threshold): array
    {
        return Mailbox::query()
            ->where(['server_id' => $server->getKey(), 'remote_username' => $username])
            ->when($domain !== null, fn ($query) => $query->where('domain', $domain))
            ->get()
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
