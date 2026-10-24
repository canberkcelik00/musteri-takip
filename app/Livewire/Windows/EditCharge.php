<?php

namespace App\Livewire\Windows;

use App\Actions\Charges\UpdateCharge;
use App\Actions\Reminders\SendRenewalReminder;
use App\Enums\ChargeStatus;
use App\Exceptions\InvalidCharge;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\Charge;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Changes the amount and due date of an open charge through UpdateCharge. When the charge has allocations the amount
 * is locked and only the due date is sent. Split, reminder and cancel sit in the "Borç işlemleri" section and open
 * their own windows (docs/UI.md §2 Kural 6 and 7).
 */
class EditCharge extends Component
{
    use ReportsDomainErrors;

    public const string CHARGES_CHANGED_EVENT = 'charges-changed';

    #[Locked]
    public int $chargeId = 0;

    public string $amount = '';

    public string $due_date = '';

    public function mount(int $charge): void
    {
        $record = Charge::query()->findOrFail($charge);

        $this->chargeId = $record->id;
        $this->amount = Money::formatAmount($record->amount);
        $this->due_date = $record->due_date->toDateString();
    }

    public function save(UpdateCharge $updateCharge): void
    {
        $charge = $this->charge();

        $this->validate([
            'amount' => ['required', new MoneyAmountRule],
            'due_date' => ['required', 'date'],
        ], attributes: ['amount' => __('charges.amount'), 'due_date' => __('charges.due_date')]);

        $attributes = ['due_date' => $this->due_date];

        if ($charge->allocatedAmount() === 0) {
            $attributes['amount'] = (int) MoneyAmountRule::minorUnits($this->amount);
        }

        try {
            $updateCharge->handle($charge, $attributes);
        } catch (InvalidCharge $exception) {
            $this->reportDomainError($exception, ['amount', 'due_date']);

            return;
        }

        $this->dispatch(self::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('charges.updated'));
    }

    public function render(SendRenewalReminder $sendRenewalReminder): View
    {
        $charge = $this->charge();
        $open = $charge->status === ChargeStatus::Open;
        $free = $open && $charge->allocatedAmount() === 0;

        return view('livewire.windows.edit-charge', [
            'charge' => $charge,
            'locked' => $charge->allocatedAmount() > 0,
            'canSplit' => $free,
            'canCancel' => $free,
            'canRemind' => $open && $sendRenewalReminder->isEligible($charge),
        ]);
    }

    private function charge(): Charge
    {
        return Charge::query()->withSum('allocations', 'amount')->findOrFail($this->chargeId);
    }
}
