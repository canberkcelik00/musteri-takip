<?php

namespace App\Actions\Domains;

use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Models\CustomerServiceItem;
use App\Support\Dns\HostName;
use App\Support\Dns\RegistrationLookup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read the registration end and the registrar of the domains of active domain services (G-16.4, same
 * pattern as {@see CheckSslCertificates}): only domains not tried for 24 hours are looked at, never
 * tried first, then the oldest attempt. A failed read counts as an attempt but keeps what was known.
 *
 * A run stops once its time budget is used up (after the domain in progress) and the rest waits for
 * the next run. A domain used by several items is read once and written to all of them.
 */
class CheckRegistrations
{
    private const RECHECK_HOURS = 24;

    public function __construct(private readonly RegistrationLookup $lookup) {}

    public function handle(int $budgetSeconds = 30): DomainCheckResult
    {
        $now = Carbon::now(config('app.timezone'))->startOfSecond();
        $due = $this->due($now);

        $startedAt = hrtime(true);
        $checked = 0;
        $failed = 0;
        $remaining = 0;

        foreach ($due->keys() as $position => $domain) {
            $registration = $this->lookup->lookup($domain);

            $values = ['registration_checked_at' => $now];

            if ($registration?->expiresOn !== null) {
                $values['registration_expires_at'] = $registration->expiresOn->toDateString();
            }

            if ($registration?->registrar !== null) {
                $values['registrar'] = $registration->registrar;
            }

            CustomerServiceItem::query()->whereIn('id', $due[$domain]->modelKeys())->update($values);

            $registration === null ? $failed++ : $checked++;

            if ((hrtime(true) - $startedAt) / 1e9 >= $budgetSeconds) {
                $remaining = $due->count() - $position - 1;

                break;
            }
        }

        return new DomainCheckResult($checked, $failed, $remaining);
    }

    /**
     * The items of active domain services whose domain is due, grouped by normalized domain, the most
     * overdue domain first.
     *
     * @return Collection<string, EloquentCollection<int, CustomerServiceItem>>
     */
    private function due(Carbon $now): Collection
    {
        $threshold = $now->copy()->subHours(self::RECHECK_HOURS);

        return CustomerServiceItem::query()
            ->where('type', ServiceType::Domain)
            ->whereHas('customerService', fn ($services) => $services->where('status', CustomerServiceStatus::Active))
            ->get()
            ->filter(fn (CustomerServiceItem $item): bool => $this->domainOf($item) !== null
                && ($item->registration_checked_at === null || $item->registration_checked_at->lessThanOrEqualTo($threshold)))
            ->groupBy(fn (CustomerServiceItem $item): string => (string) $this->domainOf($item))
            ->sortBy(fn (Collection $items): int => (int) $items->min(fn (CustomerServiceItem $item): int => $item->registration_checked_at?->getTimestamp() ?? 0));
    }

    private function domainOf(CustomerServiceItem $item): ?string
    {
        return HostName::normalize((string) ($item->details['domain'] ?? ''));
    }
}
