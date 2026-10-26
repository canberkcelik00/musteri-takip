<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\ReactivateCustomerService;
use App\Actions\CustomerServices\ReopenCustomerServiceCharge;
use App\Exceptions\InvalidCustomerService;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\CustomerService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Yeniden aktif et": while the cancelled period has not ended the dates stay; once it has, a new start date is asked
 * and, unless a charge for that period already exists, whether to create the first charge. The cancelled charge of
 * the period can be reopened when there is one. Only calls ReactivateCustomerService.
 */
class ReactivateService extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $serviceId = 0;

    public string $start_date = '';

    public string $create_first_charge = '1';

    public bool $reopen_upcoming_charge = true;

    public function mount(int $service): void
    {
        $this->serviceId = CustomerService::query()->findOrFail($service)->id;
        $this->start_date = today()->toDateString();
    }

    public function save(ReactivateCustomerService $reactivateCustomerService): void
    {
        $service = $this->service();
        $periodEnded = $this->periodEnded($service);

        $this->validate([
            'start_date' => [$periodEnded ? 'required' : 'nullable', 'date'],
        ], attributes: ['start_date' => __('customer_services.reactivate_new_start_date')]);

        $attributes = [
            'reopen_upcoming_charge' => $this->canReopen($service, $this->periodStart($service)) && $this->reopen_upcoming_charge,
            'create_first_charge' => $this->canCreateFirstCharge($service, $this->periodStart($service)) ? $this->create_first_charge === '1' : true,
        ];

        if ($periodEnded && $this->start_date !== '') {
            $attributes['start_date'] = $this->start_date;
        }

        try {
            $reactivateCustomerService->handle($service, $attributes);
        } catch (InvalidCustomerService $exception) {
            $this->reportDomainError($exception, ['start_date']);

            return;
        }

        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customer_services.reactivated'));
    }

    public function render(): View
    {
        $service = $this->service();
        $start = $this->periodStart($service);

        return view('livewire.windows.reactivate-service', [
            'service' => $service,
            'periodEnded' => $this->periodEnded($service),
            'canCreateFirstCharge' => $this->canCreateFirstCharge($service, $start),
            'canReopen' => $this->canReopen($service, $start),
            'reopenAmount' => (new Money($service->price, $service->currency))->format(),
        ]);
    }

    private function service(): CustomerService
    {
        return CustomerService::query()->with('customer')->findOrFail($this->serviceId);
    }

    private function periodEnded(CustomerService $service): bool
    {
        return $service->end_date->toDateString() <= Carbon::today(config('app.timezone'))->toDateString();
    }

    /**
     * The start of the period that would be billed: the service's end date while it has not ended, the new start
     * date once it has.
     */
    private function periodStart(CustomerService $service): ?string
    {
        if (! $this->periodEnded($service)) {
            return $service->end_date->toDateString();
        }

        return filled($this->start_date) ? Carbon::parse($this->start_date)->toDateString() : null;
    }

    private function canCreateFirstCharge(CustomerService $service, ?string $start): bool
    {
        return $this->periodEnded($service)
            && ! $service->is_free
            && $start !== null
            && $service->charges()->whereDate('period_start', $start)->doesntExist();
    }

    private function canReopen(CustomerService $service, ?string $start): bool
    {
        return ! $service->is_free
            && $start !== null
            && app(ReopenCustomerServiceCharge::class)->find($service, $start) !== null;
    }
}
