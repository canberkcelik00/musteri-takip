<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\UpdateCustomerService;
use App\Actions\Servers\BuildUsageReport;
use App\Enums\Currency;
use App\Enums\CustomerServiceStatus;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\CustomerService;
use App\Models\CustomerServiceItem;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Edits a customer's service: price, currency, dates, notes and the details of its items. Price, currency and dates
 * are locked for a cancelled service. The rarer actions sit in the "Hizmet işlemleri" section and open their own
 * windows; the dangerous ones (İptal et, Sil) are on a row of their own at its end (docs/UI.md §2 Kural 6 and 7).
 */
class EditService extends Component
{
    use AuthorizesPermission;
    use ReportsDomainErrors;

    #[Locked]
    public int $serviceId = 0;

    public string $price = '';

    public string $currency = 'TRY';

    public string $start_date = '';

    public string $end_date = '';

    public string $notes = '';

    /** @var array<int|string, array{details?: array<string, ?string>, license_key?: ?string}> keyed by item id */
    public array $items = [];

    public function mount(int $service): void
    {
        $record = CustomerService::query()->with('items')->findOrFail($service);

        $this->serviceId = $record->id;
        $this->price = Money::formatAmount($record->price);
        $this->currency = $record->currency->value;
        $this->start_date = $record->start_date->toDateString();
        $this->end_date = $record->end_date->toDateString();
        $this->notes = (string) $record->notes;
        $this->items = $record->items->mapWithKeys(fn (CustomerServiceItem $item): array => [
            $item->id => ['details' => $item->details ?? [], 'license_key' => $item->license_key],
        ])->all();
    }

    public function save(UpdateCustomerService $updateCustomerService): void
    {
        $service = $this->service();
        $locked = $service->status === CustomerServiceStatus::Cancelled;

        $this->validate([
            'price' => [$locked ? 'nullable' : 'required', new MoneyAmountRule],
            'currency' => [$locked ? 'nullable' : 'required', Rule::enum(Currency::class)],
            'start_date' => [$locked ? 'nullable' : 'required', 'date'],
            'end_date' => [$locked ? 'nullable' : 'required', 'date'],
            'notes' => ['nullable', 'string'],
            'items.*.details.*' => ['nullable', 'string', 'max:255'],
            'items.*.license_key' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'price' => __('customer_services.price'),
            'currency' => __('money.currency'),
            'start_date' => __('customer_services.start_date'),
            'end_date' => __('customer_services.end_date'),
        ]);

        $attributes = ['notes' => trim($this->notes) === '' ? null : trim($this->notes), 'items' => $this->cleanItems($service)];

        if (! $locked) {
            $attributes['price'] = (int) MoneyAmountRule::minorUnits($this->price);
            $attributes['currency'] = Currency::from($this->currency);
            $attributes['start_date'] = $this->start_date;
            $attributes['end_date'] = $this->end_date;
        }

        try {
            $updateCustomerService->handle($service, $attributes);
        } catch (InvalidCustomerService $exception) {
            $this->reportDomainError($exception, ['price', 'start_date', 'end_date']);

            return;
        }

        $this->dispatch(AssignService::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customer_services.updated'));
    }

    public function render(): View
    {
        $service = $this->service();
        $builder = app(BuildUsageReport::class);

        $usage = [];

        foreach ($service->items as $item) {
            if ($item->server_id === null || $item->remote_key === null) {
                continue;
            }

            $usage[$item->id] = [
                'summary' => $builder->handle($item)?->summary(),
                'otherDomains' => $item->otherDomains(),
            ];
        }

        return view('livewire.windows.edit-service', [
            'service' => $service,
            'locked' => $service->status === CustomerServiceStatus::Cancelled,
            'active' => $service->status === CustomerServiceStatus::Active,
            'inUse' => $service->isInUse(),
            'usage' => $usage,
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
            'currencyLabel' => (Currency::tryFrom($this->currency) ?? Currency::Try)->getLabel(),
        ]);
    }

    private function service(): CustomerService
    {
        return CustomerService::query()->with('items.server')->findOrFail($this->serviceId);
    }

    /**
     * The item fields as typed, empty ones as null, only for the service's own items.
     *
     * @return array<int, array{details: array<string, ?string>, license_key: ?string}>
     */
    private function cleanItems(CustomerService $service): array
    {
        $items = [];

        foreach ($service->items as $item) {
            $fields = $this->items[$item->id] ?? [];
            $details = $item->details ?? [];

            foreach ($item->type->customerDetailKeys() as $key) {
                $value = trim((string) ($fields['details'][$key] ?? ''));
                $details[$key] = $value === '' ? null : $value;
            }

            $licenseKey = $item->type === ServiceType::License ? trim((string) ($fields['license_key'] ?? '')) : trim((string) $item->license_key);

            $items[$item->id] = ['details' => $details, 'license_key' => $licenseKey === '' ? null : $licenseKey];
        }

        return $items;
    }
}
