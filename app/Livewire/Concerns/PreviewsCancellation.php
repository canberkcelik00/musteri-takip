<?php

namespace App\Livewire\Concerns;

use App\Actions\CustomerServices\PreviewCustomerServiceCancellation;
use App\Models\Charge;
use App\Models\CustomerService;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * What cancelling a service (or marking it free) does to its charges: the upcoming period's charge that can be
 * cancelled along with it, and the overdue and other open charges that stay (docs/UI.md §5).
 */
trait PreviewsCancellation
{
    /**
     * @return array{cancellable: ?string, overdue: ?string, open: ?string}
     */
    protected function cancellationPreview(CustomerService $service): array
    {
        $preview = app(PreviewCustomerServiceCancellation::class)->handle($service);

        return [
            'cancellable' => $preview['cancellable']->isNotEmpty() ? $this->totalOf($preview['cancellable']) : null,
            'overdue' => $preview['overdue']->isNotEmpty() ? $this->totalOf($preview['overdue']) : null,
            'open' => $preview['open']->isNotEmpty() ? $this->totalOf($preview['open']) : null,
        ];
    }

    /**
     * @param  Collection<int, Charge>  $charges
     */
    private function totalOf(Collection $charges): string
    {
        return Money::formatTotals(Money::sumByCurrency(
            $charges->map(fn (Charge $charge): Money => new Money($charge->remainingAmount(), $charge->currency)),
        ));
    }
}
