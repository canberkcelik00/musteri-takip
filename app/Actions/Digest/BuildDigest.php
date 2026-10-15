<?php

namespace App\Actions\Digest;

use App\Actions\Reminders\SendRenewalReminders;
use App\Enums\AllocationSource;
use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The sections of the digest e-mail (docs/PRD.md "Gecikmiş borçlar ve özet e-postası"),
 * without row limits. Archived customers and cancelled records do not appear.
 *
 * - new_charges: charges created in [$since, $now), not cancelled. Each one
 *   carries `credit_balance_amount`: what was applied to it from the credit balance (allocations
 *   with source `credit_balance`, at any time), so the e-mail can say it was closed from the balance,
 *   and `payment_badge`: its payment state today ({@see self::paymentBadge()}).
 * - free_decisions: free services awaiting a continuation decision; the scope only counts active
 *   services, so cancelled ones are left out.
 * - overdue: Charge::overdue() + unarchivedCustomer(), the same query as the "Bugün" page's
 *   overdue section (ListTodayTasks).
 * - missing_reminder_email: renewal charges whose customer reminder cannot be sent for lack of a
 *   valid primary contact address; only while the reminders are on.
 */
class BuildDigest
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{new_charges: Collection<int, Charge>, free_decisions: Collection<int, CustomerService>, overdue: Collection<int, Charge>, missing_reminder_email: Collection<int, Charge>}
     */
    public function handle(CarbonInterface $since, CarbonInterface $now): array
    {
        return [
            'new_charges' => $this->newCharges($since, $now),
            'free_decisions' => $this->freeDecisions(),
            'overdue' => $this->overdue(),
            'missing_reminder_email' => $this->missingReminderEmail(),
        ];
    }

    /**
     * @param  array{new_charges: Collection<int, Charge>, free_decisions: Collection<int, CustomerService>, overdue: Collection<int, Charge>, missing_reminder_email: Collection<int, Charge>}  $digest
     */
    public static function isEmpty(array $digest): bool
    {
        return $digest['new_charges']->isEmpty()
            && $digest['free_decisions']->isEmpty()
            && $digest['overdue']->isEmpty()
            && $digest['missing_reminder_email']->isEmpty();
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
            ->get()
            ->each(fn (Charge $charge): Charge => $charge->setAttribute('payment_badge', self::paymentBadge($charge)));
    }

    /**
     * The payment state of a new charge as it stands now, with the colors and labels of
     * ChargeStatusBadge, limited to three states: paid, partially paid, closed from the credit
     * balance. Overdue and due soon are not shown here (overdue charges have their own section);
     * an open charge with nothing allocated has no badge.
     *
     * @return array{state: string, label: string, color: string}|null
     */
    public static function paymentBadge(Charge $charge): ?array
    {
        if ($charge->status === ChargeStatus::Paid) {
            return (int) $charge->getAttribute('credit_balance_amount') >= $charge->amount
                ? ['state' => 'closed_from_credit', 'label' => __('digest.closed_from_credit_badge'), 'color' => 'success']
                : ['state' => 'paid', 'label' => ChargeStatus::Paid->getLabel(), 'color' => 'success'];
        }

        if ($charge->isPartiallyPaid()) {
            return ['state' => 'partially_paid', 'label' => __('charges.partially_paid'), 'color' => 'warning'];
        }

        return null;
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
     * Renewal charges waiting for a customer reminder that cannot be sent because the primary
     * contact has no valid e-mail address (G-5B.1). Empty while the reminders are off. Uses the
     * same address check as the sender, so the warning and the skipping always agree.
     *
     * @return Collection<int, Charge>
     */
    private function missingReminderEmail(): Collection
    {
        if (! $this->settings->customerRemindersEnabled()) {
            return new Collection;
        }

        return Charge::query()
            ->awaitingReminder()
            ->with(['customer.primaryContact', 'customerService'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Charge $charge): bool => SendRenewalReminders::recipientFor($charge) === null)
            ->values();
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
