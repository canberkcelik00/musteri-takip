<?php

namespace App\Actions\CustomerServices;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\CustomerService;
use Illuminate\Support\Collection;

class PreviewCustomerServiceCancellation
{
    public function __construct(private CancelUpcomingCustomerServiceCharges $cancelUpcomingCustomerServiceCharges) {}

    /**
     * Sort the open charges of the customer service into those cancelling or marking it
     * free would cancel (the upcoming period's unpaid charge, see
     * CancelUpcomingCustomerServiceCharges), and those that stay: overdue ones and other
     * open ones. Nothing is written; the cancel and mark-free windows show the result.
     *
     * @return array{cancellable: Collection<int, Charge>, overdue: Collection<int, Charge>, open: Collection<int, Charge>}
     */
    public function handle(CustomerService $customerService): array
    {
        $cancellable = $this->cancelUpcomingCustomerServiceCharges->query($customerService)->get();

        $staying = $customerService->charges()
            ->where('status', ChargeStatus::Open)
            ->withSum('allocations', 'amount')
            ->get()
            ->reject(fn (Charge $charge): bool => $cancellable->contains('id', $charge->id))
            ->filter(fn (Charge $charge): bool => $charge->remainingAmount() > 0);

        return [
            'cancellable' => $cancellable,
            'overdue' => $staying->filter(fn (Charge $charge): bool => $charge->isOverdue())->values(),
            'open' => $staying->reject(fn (Charge $charge): bool => $charge->isOverdue())->values(),
        ];
    }
}
