<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\CancelCustomerService;
use App\Livewire\Concerns\PreviewsCancellation;
use App\Models\CustomerService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "İptal et": cancels an active service through CancelCustomerService, optionally with the upcoming period's charge.
 */
class CancelService extends Component
{
    use PreviewsCancellation;

    #[Locked]
    public int $serviceId = 0;

    public string $cancelled_on = '';

    public string $cancel_note = '';

    public bool $cancel_upcoming_charges = true;

    public function mount(int $service): void
    {
        $this->serviceId = CustomerService::query()->findOrFail($service)->id;
        $this->cancelled_on = today()->toDateString();
    }

    public function save(CancelCustomerService $cancelCustomerService): void
    {
        $this->validate([
            'cancelled_on' => ['required', 'date'],
            'cancel_note' => ['nullable', 'string'],
        ], attributes: ['cancelled_on' => __('customer_services.cancelled_on')]);

        $cancelCustomerService->handle(
            CustomerService::query()->findOrFail($this->serviceId),
            $this->cancelled_on,
            trim($this->cancel_note) === '' ? null : trim($this->cancel_note),
            $this->cancel_upcoming_charges,
        );

        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customer_services.cancelled'));
    }

    public function render(): View
    {
        return view('livewire.windows.cancel-service', [
            'preview' => $this->cancellationPreview(CustomerService::query()->findOrFail($this->serviceId)),
        ]);
    }
}
