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
 * satırı"): for an item linked to a server account the worst threshold badge and whether the account is
 * suspended. Reads only what the refreshes stored, in a fixed number of queries however many services the
 * customer has; a cancelled service gets none.
 */
class BuildServiceCardSummaries
{
    public function __construct(private readonly BuildUsageReport $usageReport) {}

    /**
     * @param  Collection<int, CustomerService>  $services  with `items.server` loaded
     * @return array<int, list<array{item: int, badge: array{text: string, tone: string}|null, suspended: bool}>> keyed by service id
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
                    'badge' => $badge === null ? null : ['text' => $badge['text'], 'tone' => StatusBadges::toneOfColor($badge['level']->getColor())],
                    'suspended' => $report !== null && isset($suspended[LinkedAccounts::keyOf($item->server_id, LinkedAccounts::usernameOf($item))]),
                ];
            })->all();
        }

        return $summaries;
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
