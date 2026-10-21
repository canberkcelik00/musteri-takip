<?php

namespace App\Actions\Servers;

use App\Enums\ServerType;
use App\Enums\UsageLevel;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Models\ServerUsage;
use App\Support\Servers\ResourceLabel;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Reads the stored totals and account list of a WHM server and prepares them for the server
 * "Kullanım" window (docs/UI.md "Sunucu kullanım penceresi", docs/DECISIONS.md T-120). Reads only
 * what the refresh stored; never calls the server. Levels come from the same threshold and rules
 * as the warnings; a limit the server did not tell (`limit_read` false) gives no level. The
 * accounts come problems first, then the biggest disk use.
 */
class BuildServerUsageReport
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Server $server): ?ServerUsageReport
    {
        if ($server->type !== ServerType::Whm) {
            return null;
        }

        $usage = ServerUsage::query()->where('server_id', $server->getKey())->first();
        $threshold = $this->settings->usageWarningPercent();
        $stored = collect($usage->accounts ?? []);
        $customers = $this->customersByUsername($server, $stored->pluck('username')->all());

        $accounts = $stored
            ->map(fn (array $account): ServerAccountRow => $this->row($account, $customers->get($account['username'], []), $threshold))
            ->sort(fn (ServerAccountRow $a, ServerAccountRow $b): int => [$b->level()?->severity() ?? 0, $b->disk->used, $a->username] <=> [$a->level()?->severity() ?? 0, $a->disk->used, $b->username])
            ->values();

        $resources = collect($usage->resources ?? []);
        $disk = $resources->firstWhere('id', 'reseller_disk');
        $bandwidth = $resources->firstWhere('id', 'reseller_bandwidth');

        return new ServerUsageReport(
            serverName: $server->name,
            hostname: $server->hostname,
            refreshedAt: $usage?->refreshed_at,
            failedAt: $usage?->failed_at,
            failureReason: $usage?->failure_reason,
            gauges: $resources->map(fn (array $resource): UsageMeter => new UsageMeter(
                id: $resource['id'],
                label: ResourceLabel::for($resource['id'], $resource['label']),
                used: $resource['used'],
                limit: $resource['limit'],
                isBytes: $resource['is_bytes'],
                level: $resource['limit_read'] ? UsageLevel::of($resource['used'], $resource['limit'], $threshold) : null,
                limitKnown: $resource['limit_read'],
            ))->all(),
            limitsRead: $resources->isNotEmpty() && $resources->every(fn (array $resource): bool => $resource['limit_read']),
            accounts: $accounts->all(),
            activeCount: $accounts->reject(fn (ServerAccountRow $row): bool => $row->suspended)->count(),
            suspendedCount: $accounts->filter(fn (ServerAccountRow $row): bool => $row->suspended)->count(),
            linkedCount: $accounts->reject(fn (ServerAccountRow $row): bool => $row->customers === [])->count(),
            unlinkedCount: $accounts->filter(fn (ServerAccountRow $row): bool => $row->customers === [])->count(),
            allocatedDisk: (int) $stored->sum(fn (array $account): int => $account['disk_limit'] ?? 0),
            unlimitedDiskAccounts: $stored->filter(fn (array $account): bool => $account['disk_limit'] === null)->count(),
            resellerDiskLimit: ($disk['limit_read'] ?? false) ? $disk['limit'] : null,
            allocatedBandwidth: (int) $stored->sum(fn (array $account): int => $account['bw_limit'] ?? 0),
            unlimitedBandwidthAccounts: $stored->filter(fn (array $account): bool => $account['bw'] !== null && $account['bw_limit'] === null)->count(),
            resellerBandwidthLimit: ($bandwidth['limit_read'] ?? false) ? $bandwidth['limit'] : null,
        );
    }

    /**
     * @param  array{username: string, domain: string, plan: string|null, suspended: bool, suspend_reason: string|null, disk: int, disk_limit: int|null, bw: int|null, bw_limit: int|null}  $account
     * @param  list<array{id: int, short_name: string, archived: bool}>  $customers
     */
    private function row(array $account, array $customers, int $threshold): ServerAccountRow
    {
        return new ServerAccountRow(
            username: $account['username'],
            domain: $account['domain'],
            plan: $account['plan'],
            suspended: $account['suspended'],
            suspendReason: $account['suspend_reason'],
            disk: new UsageMeter('disk', __('usage.resource.disk_usage'), $account['disk'], $account['disk_limit'], true, UsageLevel::of($account['disk'], $account['disk_limit'], $threshold)),
            bandwidth: $account['bw'] === null
                ? null
                : new UsageMeter('bandwidth', __('usage.resource.bandwidth'), $account['bw'], $account['bw_limit'], true, UsageLevel::of($account['bw'], $account['bw_limit'], $threshold)),
            customers: $customers,
        );
    }

    /**
     * The customers whose services are linked to each account of the server, by cPanel user;
     * a customer that is archived is still named, marked as such.
     *
     * @param  list<string>  $usernames
     * @return Collection<int|string, array<int, array{id: int, short_name: string, archived: bool}>>
     */
    private function customersByUsername(Server $server, array $usernames): Collection
    {
        return CustomerServiceItem::query()
            ->where('server_id', $server->getKey())
            ->whereIn('remote_key', $usernames)
            ->with('customerService.customer')
            ->get()
            ->groupBy('remote_key')
            ->map(fn (Collection $items): array => $items
                ->map(fn (CustomerServiceItem $item) => $item->customerService->customer)
                ->unique('id')
                ->sortBy('short_name')
                ->map(fn ($customer): array => ['id' => (int) $customer->getKey(), 'short_name' => $customer->short_name, 'archived' => $customer->isArchived()])
                ->values()
                ->all());
    }
}
