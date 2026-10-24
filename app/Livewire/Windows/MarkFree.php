<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\MarkCustomerServiceFree;
use App\Livewire\Concerns\PreviewsCancellation;
use App\Models\CustomerService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Ücretsiz yap": marks an active service free through MarkCustomerServiceFree, optionally cancelling the upcoming
 * period's charge; overdue and other open charges are never cancelled here.
 */
class MarkFree extends Component
{
    use PreviewsCancellation;

    #[Locked]
    public int $serviceId = 0;

    public bool $cancel_upcoming_charges = true;

    public function mount(int $service): void
    {
        $this->serviceId = CustomerService::query()->findOrFail($service)->id;
    }

    public function save(MarkCustomerServiceFree $markCustomerServiceFree): void
    {
        $service = CustomerService::query()->findOrFail($this->serviceId);

        $markCustomerServiceFree->handle($service, $this->cancel_upcoming_charges);

        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customer_services.marked_free'));
    }

    public function render(): View
    {
        return view('livewire.windows.mark-free', [
            'preview' => $this->cancellationPreview(CustomerService::query()->findOrFail($this->serviceId)),
        ]);
    }
}
