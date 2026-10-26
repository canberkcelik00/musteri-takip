<?php

namespace App\Livewire\Windows;

use App\Actions\Charges\SplitCharge as SplitChargeAction;
use App\Exceptions\InvalidCharge;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Charge;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Splits an amount off an open charge without allocations into a new charge through SplitCharge.
 */
class SplitCharge extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $chargeId = 0;

    public string $amount = '';

    public string $due_date = '';

    public string $description = '';

    public function mount(int $charge): void
    {
        $record = Charge::query()->findOrFail($charge);

        $this->chargeId = $record->id;
        $this->due_date = $record->due_date->toDateString();
    }

    public function save(SplitChargeAction $splitCharge): void
    {
        $this->validate([
            'amount' => ['required', new MoneyAmountRule],
            'due_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'amount' => __('charges.split_amount'),
            'due_date' => __('charges.split_due_date'),
            'description' => __('charges.split_description'),
        ]);

        try {
            $splitCharge->handle(
                $this->actor(),
                Charge::query()->findOrFail($this->chargeId),
                (int) MoneyAmountRule::minorUnits($this->amount),
                $this->due_date,
                trim($this->description) === '' ? null : trim($this->description),
            );
        } catch (InvalidCharge $exception) {
            $this->reportDomainError($exception, ['amount', 'due_date', 'description']);

            return;
        }

        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('charges.split_done'));
    }

    public function render(): View
    {
        $charge = Charge::query()->findOrFail($this->chargeId);

        return view('livewire.windows.split-charge', ['charge' => $charge]);
    }
}
