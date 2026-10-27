<?php

namespace App\Actions\Servers;

use App\Enums\CustomerServiceStatus;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Models\ServerUsage;
use App\Support\Servers\LinkedAccounts;
use App\Support\Ui\StatusBadges;
use Illuminate\Support\Collection;

/**
 * What each service row of the customer card shows below the name (docs/UI.md "Müşteri kartı, Hizmetler
 * satırı"): per item the filled details other than the domain, and for an item linked to a server account the
 * stored usage line, the worst threshold badge and whether the account is suspended. Reads only what the
 * refreshes stored, in a fixed number of queries however many services the customer has; a cancelled service
 * gets its details only.
 */
class BuildServiceCardSummaries
{
    public function __construct(private readonly BuildUsageReport $usageReport) {}

    /**
     * @param  Collection<int, CustomerService>  $services  with `items.server` loaded
     * @return array<int, list<array{item: int, details: string|null, usage: array{read: bool, cells: array<string, string>, stale: string|null, domains: list<string>}|null, badge: array{text: string, tone: string}|null, suspended: bool}>> keyed by service id
     */
    public function handle(Collection $services): array
    {
        $active = $services->where('status', CustomerServiceStatus::Active);
        $reports = $this->usageReport->handleMany($active->flatMap(fn (CustomerService $service): Collection => $service->items));
        $suspended = $this->suspendedAccounts($active);
        $summaries = [];

        foreach ($services as $service) {
            $isActive = $service->status === CustomerServiceStatus::Active;

            $summaries[$service->id] = $service->items->map(function (CustomerServiceItem $item) use ($isActive, $reports, $suspended): array {
                $report = $isActive ? ($reports[$item->id] ?? null) : null;
                $badge = $report?->badge();

                return [
                    'item' => $item->id,
                    'details' => $this->details($item),
                    'usage' => $report === null ? null : [
                        'read' => $report->hasData(),
                        'cells' => $report->cardCells(),
                        'stale' => $report->staleNote(),
                        'domains' => $this->domains($item, $report),
                    ],
                    'badge' => $badge === null ? null : ['text' => $badge['text'], 'tone' => StatusBadges::toneOfColor($badge['level']->getColor())],
                    'suspended' => $report !== null && isset($suspended[LinkedAccounts::keyOf($item->server_id, LinkedAccounts::usernameOf($item))]),
                ];
            })->all();
        }

        return $summaries;
    }

    /**
     * The domains tied to the account: the item's own domain, then the others the refresh read from it.
     *
     * @return list<string>
     */
    private function domains(CustomerServiceItem $item, UsageReport $report): array
    {
        $own = $item->details['domain'] ?? null;

        return array_values(array_unique(array_filter([...(filled($own) ? [$own] : []), ...$report->domains])));
    }

    /**
     * "Sunucu: Logi WHM · cPanel kullanıcı adı: logi": the item's filled details in the type's order, without the
     * domain (the row shows it already). The license key is not a detail and never appears.
     */
    private function details(CustomerServiceItem $item): ?string
    {
        $parts = [];

        foreach ($item->type->customerDetailKeys() as $key) {
            $value = $item->details[$key] ?? null;

            if ($key !== 'domain' && filled($value)) {
                $parts[] = $item->type->customerDetailLabel($key).': '.$value;
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The suspended WHM accounts of the services' servers as a set of account keys, from one read of the servers'
     * stored account lists.
     *
     * @param  Collection<int, CustomerService>  $services
     * @return array<string, true>
     */
    private function suspendedAccounts(Collection $services): array
    {
        $serverIds = $services->flatMap(fn (CustomerService $service): Collection => $service->items)->pluck('server_id')->filter()->unique()->all();

        if ($serverIds === []) {
            return [];
        }

        $suspended = [];

        foreach (ServerUsage::query()->whereIn('server_id', $serverIds)->get(['server_id', 'accounts']) as $usage) {
            foreach ($usage->accounts ?? [] as $account) {
                if ($account['suspended']) {
                    $suspended[LinkedAccounts::keyOf($usage->server_id, $account['username'])] = true;
                }
            }
        }

        return $suspended;
    }
}
