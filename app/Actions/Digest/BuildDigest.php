<?php

namespace App\Actions\Digest;

use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sections of the digest e-mail (docs/PRD.md "Gecikmiş borçlar ve günlük özet e-posta"),
 * without row limits. Archived customers and cancelled records do not appear.
 *
 * - new_charges: charges created in [$since, $now), not cancelled. Each one
 *   carries `credit_balance_amount`: what was applied to it from the credit balance (allocations
 *   with source `credit_balance`, at any time), so the e-mail can say it was closed from the balance.
 * - free_decisions: free services awaiting a continuation decision; the scope only counts active
 *   services, so cancelled ones are left out.
 * - overdue: Charge::overdue() + unarchivedCustomer(), the same query as the "Bugün" page's
 *   overdue section (ListTodayTasks).
 */
class BuildDigest
{
    /**
     * @return array{new_charges: Collection<int, Charge>, free_decisions: Collection<int, CustomerService>, overdue: Collection<int, Charge>}
     */
    public function handle(CarbonInterface $since, CarbonInterface $now): array
    {
        return [
            'new_charges' => $this->newCharges($since, $now),
            'free_decisions' => $this->freeDecisions(),
            'overdue' => $this->overdue(),
        ];
    }

    /**
     * @param  array{new_charges: Collection<int, Charge>, free_decisions: Collection<int, CustomerService>, overdue: Collection<int, Charge>}  $digest
     */
    public static function isEmpty(array $digest): bool
    {
        return $digest['new_charges']->isEmpty()
            && $digest['free_decisions']->isEmpty()
            && $digest['overdue']->isEmpty();
    }

    /**
     * @return Collection<int, Charge>
     */
    private function newCharges(CarbonInterface $since, CarbonInterface $now): Collection
    {
        return Charge::query()
            ->createdBetween($since, $now)
            ->where('status', '!=', ChargeStatus::Cancelled)
            ->unarchivedCustomer()
            ->with('customer')
            ->withSum('allocations', 'amount')
            ->withSum(
                ['allocations as credit_balance_amount' => fn (Builder $query): Builder => $query->where('source', AllocationSource::CreditBalance)],
                'amount',
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CustomerService>
     */
    private function freeDecisions(): Collection
    {
        return CustomerService::query()
            ->awaitingFreeDecision()
            ->whereHas('customer', fn (Builder $query): Builder => $query->notArchived())
            ->with('customer')
            ->orderBy('end_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Charge>
     */
    private function overdue(): Collection
    {
        return Charge::query()
            ->overdue()
            ->unarchivedCustomer()
            ->with('customer')
            ->withSum('allocations', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }
}
