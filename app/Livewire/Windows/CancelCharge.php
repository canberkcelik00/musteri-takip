<?php

namespace App\Livewire\Windows;

use App\Actions\Charges\CancelCharge as CancelChargeAction;
use App\Exceptions\InvalidCharge;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Charge;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cancels an open charge without allocations through CancelCharge; the reason is required.
 */
class CancelCharge extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $chargeId = 0;

    public string $reason = '';

    public function mount(int $charge): void
    {
        $this->chargeId = Charge::query()->findOrFail($charge)->id;
    }

    public function save(CancelChargeAction $cancelCharge): void
    {
        $this->validate(['reason' => ['required', 'string']], attributes: ['reason' => __('charges.cancel_reason')]);

        try {
            $cancelCharge->handle(Charge::query()->findOrFail($this->chargeId), $this->reason);
        } catch (InvalidCharge $exception) {
            $this->reportDomainError($exception, ['reason']);

            return;
        }

        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('charges.cancelled'));
    }

    public function render(): View
    {
        return view('livewire.windows.cancel-charge');
    }
}
