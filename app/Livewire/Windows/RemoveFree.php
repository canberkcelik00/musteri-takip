<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\RemoveCustomerServiceFreeFlag;
use App\Enums\CustomerServiceStatus;
use App\Exceptions\InvalidCustomerService;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\CustomerService;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Ücretli yap": makes a free service paid again through RemoveCustomerServiceFreeFlag. Opened from the service's edit
 * window and from the free-decision rows of "Bugün", where the list may be stale: the state is checked again when the
 * window saves and a warning replaces the work.
 */
class RemoveFree extends Component
{
    use ReportsDomainErrors;

    #[Locked]
    public int $serviceId = 0;

    public string $price = '';

    public function mount(int $service): void
    {
        $record = CustomerService::query()->findOrFail($service);

        $this->serviceId = $record->id;
        $this->price = Money::formatAmount($record->price);
    }

    public function save(RemoveCustomerServiceFreeFlag $removeCustomerServiceFreeFlag): void
    {
        $this->validate(['price' => ['required', new MoneyAmountRule]], attributes: ['price' => __('customer_services.new_price')]);

        $service = CustomerService::query()->findOrFail($this->serviceId);

        if ($service->status !== CustomerServiceStatus::Active || ! $service->is_free) {
            $this->dispatch('close-window');
            $this->dispatch('notify', message: __('customer_services.free_action_not_applied'), tone: 'danger');

            return;
        }

        try {
            $charge = $removeCustomerServiceFreeFlag->handle($service, (int) MoneyAmountRule::minorUnits($this->price));
        } catch (InvalidCustomerService $exception) {
            $this->reportDomainError($exception, ['price']);

            return;
        }

        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: $charge === null
            ? __('customer_services.removed_free')
            : __('customer_services.removed_free_charge', ['amount' => (new Money($charge->amount, $charge->currency))->format()]));
    }

    public function render(): View
    {
        return view('livewire.windows.remove-free', ['service' => CustomerService::query()->findOrFail($this->serviceId)]);
    }
}
