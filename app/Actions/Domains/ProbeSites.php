<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CustomerServiceItem;
use App\Models\SiteIncident;
use App\Support\Dns\HostName;
use App\Support\Web\HttpProbe;
use App\Support\Web\ProbeResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ask the front page of every active hosting domain whether the site still answers (G-16.6, same pattern as
 * {@see CheckHostingPoints}). Only domains not probed for 14 minutes are asked (the cron runs every 15, so a probe is
 * never skipped for coming a few seconds early), never probed first, then the oldest. Two failed probes in a row open
 * a {@see SiteIncident}; the first answer closes it. A domain that cannot be asked at all changes nothing but the
 * attempt time. A run stops once its time budget is used up; the rest waits for the next run.
 */
class ProbeSites
{
    private const RECHECK_MINUTES = 14;

    /**
     * Failed probes in a row that open an incident: one stray failure is not "down".
     */
    public const int FAILURES_TO_OPEN = 2;

    public function __construct(private readonly HttpProbe $probe) {}

    public function handle(int $budgetSeconds = 30): DomainCheckResult
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();

        $this->closeStale($now);

        $due = $this->due($now);

        $startedAt = hrtime(true);
        $checked = 0;
        $failed = 0;
        $remaining = 0;

        foreach ($due->keys() as $position => $domain) {
            $items = $due[$domain];
            $result = $this->probe->probe($domain);

            if ($result === null) {
                CustomerServiceItem::query()->whereIn('id', $items->modelKeys())->update(['site_checked_at' => $now]);
                $failed++;
            } else {
                foreach ($items as $item) {
                    $this->record($item, $domain, $result, $now);
                }

                $checked++;
            }

            if ((hrtime(true) - $startedAt) / 1e9 >= $budgetSeconds) {
                $remaining = $due->count() - $position - 1;

                break;
            }
        }

        return new DomainCheckResult($checked, $failed, $remaining);
    }

    private function record(CustomerServiceItem $item, string $domain, ProbeResult $result, Carbon $now): void
    {
        if ($result->up) {
            $item->siteIncidents()->open()->update(['ended_at' => $now]);
            CustomerServiceItem::query()->whereKey($item->id)->update(['site_failures' => 0, 'site_checked_at' => $now]);

            return;
        }

        $failures = $item->site_failures + 1;

        CustomerServiceItem::query()->whereKey($item->id)->update(['site_failures' => $failures, 'site_checked_at' => $now]);

        if ($failures >= self::FAILURES_TO_OPEN && ! $item->siteIncidents()->open()->exists()) {
            $item->siteIncidents()->create([
                'domain' => $domain,
                'started_at' => $item->site_failures === 1 && $item->site_checked_at !== null ? $item->site_checked_at : $now,
                'reason' => $result->reason,
            ]);
        }
    }

    /**
     * An incident of a site that is no longer probed (service no longer active, domain changed or removed) would stay
     * open for good; it ends now.
     */
    private function closeStale(Carbon $now): void
    {
        SiteIncident::query()->open()->with('item.customerService')->get()
            ->filter(fn (SiteIncident $incident): bool => ! $this->isProbed($incident->item, $incident->domain))
            ->each(fn (SiteIncident $incident): bool => $incident->update(['ended_at' => $now]));
    }

    private function isProbed(CustomerServiceItem $item, string $domain): bool
    {
        return $item->type === ServiceType::Hosting
            && $item->customerService->status === CustomerServiceStatus::Active
            && $this->domainOf($item) === $domain;
    }

    /**
     * The items due, grouped by domain, the most overdue group first.
     *
     * @return Collection<string, EloquentCollection<int, CustomerServiceItem>>
     */
    private function due(Carbon $now): Collection
    {
        $threshold = $now->copy()->subMinutes(self::RECHECK_MINUTES);

        return CustomerServiceItem::query()
            ->where('type', ServiceType::Hosting)
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => $this->domainOf($item) !== null
                && ($item->site_checked_at === null || $item->site_checked_at->lessThanOrEqualTo($threshold)))
            ->groupBy(fn (CustomerServiceItem $item): string => (string) $this->domainOf($item))
            ->sortBy(fn (Collection $items): int => (int) $items->min(fn (CustomerServiceItem $item): int => $item->site_checked_at?->getTimestamp() ?? 0));
    }

    private function domainOf(CustomerServiceItem $item): ?string
    {
        return HostName::normalize((string) ($item->details['domain'] ?? ''));
    }
}
