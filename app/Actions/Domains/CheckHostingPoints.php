<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Enums\DnsRecordType;
use App\Enums\ServiceType;
use App\Models\CustomerServiceItem;
use App\Models\Server;
use App\Support\Dns\DnsLookup;
use App\Support\Dns\HostName;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Find out whether the domain of a hosting item still points to the server the item is linked to
 * (G-16.5, same pattern as {@see CheckSslCertificates}): the A records of the domain are compared with
 * the addresses of the server's host name. Only domains not tried for 24 hours are looked at, never
 * tried first, then the oldest attempt. A failed read counts as an attempt but keeps the earlier answer.
 *
 * Only active hosting items linked to a server and known not to be on Cloudflare are checked: behind
 * Cloudflare the A record is never the server's, so the answer stays unknown there (a stored answer is
 * cleared) and no false alarm is raised. A run stops once its time budget is used up; the rest waits
 * for the next run.
 */
class CheckHostingPoints
{
    private const RECHECK_HOURS = 24;

    public function __construct(private readonly DnsLookup $dns) {}

    public function handle(int $budgetSeconds = 30): DomainCheckResult
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();

        $this->forgetCloudflare();

        $due = $this->due($now);
        $serverAddresses = [];

        $startedAt = hrtime(true);
        $checked = 0;
        $failed = 0;
        $remaining = 0;

        foreach ($due->keys() as $position => $key) {
            $items = $due[$key];
            $first = $items->first();

            $serverAddresses[$first->server_id] ??= $this->serverAddresses($first->server);
            $domainAddresses = $this->dns->records((string) $this->domainOf($first), DnsRecordType::A);

            $points = $serverAddresses[$first->server_id] === null || $domainAddresses === null || $domainAddresses === []
                ? null
                : array_intersect($domainAddresses, $serverAddresses[$first->server_id]) !== [];

            CustomerServiceItem::query()->whereIn('id', $items->modelKeys())->update(
                $points === null
                    ? ['points_checked_at' => $now]
                    : ['points_to_server' => $points, 'points_checked_at' => $now],
            );

            $points === null ? $failed++ : $checked++;

            if ((hrtime(true) - $startedAt) / 1e9 >= $budgetSeconds) {
                $remaining = $due->count() - $position - 1;

                break;
            }
        }

        return new DomainCheckResult($checked, $failed, $remaining);
    }

    /**
     * An answer kept for an item that has meanwhile turned out to be on Cloudflare no longer holds.
     */
    private function forgetCloudflare(): void
    {
        CustomerServiceItem::query()
            ->where('is_cloudflare', true)
            ->whereNotNull('points_to_server')
            ->update(['points_to_server' => null]);
    }

    /**
     * The items due, grouped by domain and server (the same domain may be linked to two servers), the
     * most overdue group first.
     *
     * @return Collection<string, EloquentCollection<int, CustomerServiceItem>>
     */
    private function due(Carbon $now): Collection
    {
        $threshold = $now->copy()->subHours(self::RECHECK_HOURS);

        return CustomerServiceItem::query()
            ->where('type', ServiceType::Hosting)
            ->whereNotNull('server_id')
            ->where('is_cloudflare', false)
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->with('server')
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => $this->domainOf($item) !== null
                && ($item->points_checked_at === null || $item->points_checked_at->lessThanOrEqualTo($threshold)))
            ->groupBy(fn (CustomerServiceItem $item): string => $this->domainOf($item).'@'.$item->server_id)
            ->sortBy(fn (Collection $items): int => (int) $items->min(fn (CustomerServiceItem $item): int => $item->points_checked_at?->getTimestamp() ?? 0));
    }

    /**
     * The IPv4 addresses of the server: its host name when that is one already, else the A records of
     * the host name. Null when they cannot be read.
     *
     * @return list<string>|null
     */
    private function serverAddresses(Server $server): ?array
    {
        $hostname = mb_strtolower(trim($server->hostname));

        if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return [$hostname];
        }

        $addresses = $this->dns->records($hostname, DnsRecordType::A);

        return $addresses === null || $addresses === [] ? null : $addresses;
    }

    private function domainOf(CustomerServiceItem $item): ?string
    {
        return HostName::normalize((string) ($item->details['domain'] ?? ''));
    }
}
