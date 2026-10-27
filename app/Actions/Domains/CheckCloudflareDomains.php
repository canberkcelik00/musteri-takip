<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerServiceItem;
use App\Support\Dns\NameserverLookup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Find out which domains of active services point to Cloudflare, i.e. have a name server under
 * `ns.cloudflare.com` (docs/DECISIONS.md T-169). Called on every scheduler run and only looks at
 * domains not tried for 24 hours ("henüz yapılmadıysa yap"): never tried first, then the oldest
 * attempt. A failed lookup counts as an attempt but keeps the earlier answer, so a broken domain
 * is not asked again on every run.
 *
 * A run stops once its time budget is used up (after the domain in progress) and the rest waits
 * for the next run. A domain used by several items is asked once and written to all of them.
 */
class CheckCloudflareDomains
{
    private const RECHECK_HOURS = 24;

    private const CLOUDFLARE_SUFFIX = '.ns.cloudflare.com';

    public function __construct(private readonly NameserverLookup $lookup) {}

    public function handle(int $budgetSeconds = 30): DomainCheckResult
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();
        $due = $this->due($now);

        $startedAt = hrtime(true);
        $checked = 0;
        $failed = 0;
        $remaining = 0;

        foreach ($due->keys() as $position => $domain) {
            $isCloudflare = $this->isCloudflare($domain);
            $ids = $due[$domain]->modelKeys();

            CustomerServiceItem::query()->whereIn('id', $ids)->update(
                $isCloudflare === null
                    ? ['dns_checked_at' => $now]
                    : ['is_cloudflare' => $isCloudflare, 'dns_checked_at' => $now],
            );

            $isCloudflare === null ? $failed++ : $checked++;

            if ((hrtime(true) - $startedAt) / 1e9 >= $budgetSeconds) {
                $remaining = $due->count() - $position - 1;

                break;
            }
        }

        return new DomainCheckResult($checked, $failed, $remaining);
    }

    /**
     * The items of active services whose domain is due, grouped by lower case domain, the most
     * overdue domain first.
     *
     * @return Collection<string, EloquentCollection<int, CustomerServiceItem>>
     */
    private function due(Carbon $now): Collection
    {
        $threshold = $now->copy()->subHours(self::RECHECK_HOURS);

        return CustomerServiceItem::query()
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => $this->domainOf($item) !== null
                && ($item->dns_checked_at === null || $item->dns_checked_at->lessThanOrEqualTo($threshold)))
            ->groupBy(fn (CustomerServiceItem $item): string => (string) $this->domainOf($item))
            ->sortBy(fn (Collection $items): int => (int) $items->min(fn (CustomerServiceItem $item): int => $item->dns_checked_at?->getTimestamp() ?? 0));
    }

    /**
     * The item's domain when it is a plain host name with at least one dot, lower case.
     */
    private function domainOf(CustomerServiceItem $item): ?string
    {
        $domain = mb_strtolower(trim((string) ($item->details['domain'] ?? '')));

        return preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) === 1 && str_contains($domain, '.')
            ? $domain
            : null;
    }

    /**
     * Whether the domain is on Cloudflare: the first name in the domain, then its parents down to
     * two labels, that has name servers decides. Null when a lookup failed.
     */
    private function isCloudflare(string $domain): ?bool
    {
        $labels = explode('.', $domain);

        while (count($labels) >= 2) {
            $nameservers = $this->lookup->nameserversOf(implode('.', $labels));

            if ($nameservers === null) {
                return null;
            }

            if ($nameservers !== []) {
                return collect($nameservers)->contains(fn (string $nameserver): bool => str_ends_with($nameserver, self::CLOUDFLARE_SUFFIX));
            }

            array_shift($labels);
        }

        return false;
    }
}
