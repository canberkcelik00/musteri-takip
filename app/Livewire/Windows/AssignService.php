<?php

namespace App\Livewire\Windows;

use App\Actions\CustomerServices\AssignCustomerService;
use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Exceptions\InvalidCustomerService;
use App\Livewire\Concerns\ReportsDomainErrors;
use App\Models\CatalogService;
use App\Models\Customer;
use App\Models\Package;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "Hizmet ekle" window: catalog service or package, price, currency, start date, first charge and per-item
 * details. It only calls AssignCustomerService; on the customer card the customer is fixed.
 */
class AssignService extends Component
{
    use ReportsDomainErrors;

    public const string SERVICES_CHANGED_EVENT = 'customer-services-changed';

    #[Locked]
    public int $customerId = 0;

    public string $sold = '';

    public string $price = '';

    public string $currency = 'TRY';

    public string $start_date = '';

    public bool $is_free = false;

    public string $create_first_charge = '1';

    public string $notes = '';

    /** @var array<int|string, array{details?: array<string, ?string>, license_key?: ?string}> keyed by catalog service id */
    public array $items = [];

    public function mount(int $customer): void
    {
        $this->customerId = Customer::query()->findOrFail($customer)->id;
        $this->start_date = today()->toDateString();
    }

    public function updatedSold(): void
    {
        $this->items = [];

        $sold = $this->resolveSold($this->sold);

        if ($sold === null) {
            return;
        }

        $this->price = Money::formatAmount($sold->price);
        $this->currency = $sold->currency->value;
    }

    public function save(AssignCustomerService $assignCustomerService): void
    {
        $this->validate([
            'sold' => ['required'],
            'price' => ['required', new MoneyAmountRule],
            'currency' => ['required', Rule::enum(Currency::class)],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items.*.details.*' => ['nullable', 'string', 'max:255'],
            'items.*.license_key' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'sold' => __('customer_services.sold'),
            'price' => __('customer_services.price'),
            'currency' => __('money.currency'),
            'start_date' => __('customer_services.start_date'),
        ]);

        $sold = $this->resolveSold($this->sold);

        if ($sold === null) {
            $this->addError('sold', __('validation.required', ['attribute' => __('customer_services.sold')]));

            return;
        }

        try {
            $assignCustomerService->handle(Customer::query()->findOrFail($this->customerId), $sold, [
                'start_date' => $this->start_date,
                'price' => (int) MoneyAmountRule::minorUnits($this->price),
                'currency' => Currency::from($this->currency),
                'is_free' => $this->is_free,
                'create_first_charge' => $this->create_first_charge === '1',
                'notes' => trim($this->notes) === '' ? null : trim($this->notes),
                'items' => $this->cleanItems(),
            ]);
        } catch (InvalidCustomerService $exception) {
            $this->reportDomainError($exception, ['sold', 'price', 'currency', 'start_date']);

            return;
        }

        $this->dispatch(self::SERVICES_CHANGED_EVENT);
        $this->dispatch(EditCharge::CHARGES_CHANGED_EVENT);
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customer_services.assigned'));
    }

    public function render(): View
    {
        $sold = $this->resolveSold($this->sold);

        return view('livewire.windows.assign-service', [
            'options' => array_filter([
                __('customer_services.group_services') => $this->optionsFor(CatalogService::query(), 'catalog_service'),
                __('customer_services.group_packages') => $this->optionsFor(Package::query(), 'package'),
            ]),
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
            'catalogServices' => $this->catalogServicesOf($sold),
            'currencyLabel' => (Currency::tryFrom($this->currency) ?? Currency::Try)->getLabel(),
        ]);
    }

    /**
     * Catalog services and packages in two groups; archived ones come last in their group.
     *
     * @param  Builder<CatalogService>|Builder<Package>  $query
     * @return array<string, string>
     */
    private function optionsFor(Builder $query, string $kind): array
    {
        return $query
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CatalogService|Package $record): array => [
                "{$kind}:{$record->id}" => $record->isArchived()
                    ? __('customer_services.archived_option', ['name' => $record->name])
                    : $record->name,
            ])
            ->all();
    }

    private function resolveSold(string $key): CatalogService|Package|null
    {
        [$kind, $id] = array_pad(explode(':', $key, 2), 2, null);

        return match ($kind) {
            'catalog_service' => CatalogService::query()->find($id),
            'package' => Package::query()->find($id),
            default => null,
        };
    }

    /**
     * The catalog services that get an item, each with the customer details of its type.
     *
     * @return Collection<int, CatalogService>
     */
    private function catalogServicesOf(CatalogService|Package|null $sold): Collection
    {
        if ($sold === null) {
            return new Collection;
        }

        return $sold instanceof Package
            ? $sold->catalogServices()->orderBy('catalog_services.id')->get()
            : new Collection([$sold]);
    }

    /**
     * The item fields as typed, empty ones as null, only for the catalog services of the chosen sale.
     *
     * @return array<int, array{details: array<string, ?string>, license_key: ?string}>
     */
    private function cleanItems(): array
    {
        $allowed = $this->catalogServicesOf($this->resolveSold($this->sold))->keyBy('id');
        $items = [];

        foreach ($this->items as $catalogServiceId => $fields) {
            $catalogService = $allowed->get((int) $catalogServiceId);

            if ($catalogService === null) {
                continue;
            }

            $details = [];

            foreach ($catalogService->type->customerDetailKeys() as $key) {
                $value = trim((string) ($fields['details'][$key] ?? ''));
                $details[$key] = $value === '' ? null : $value;
            }

            $licenseKey = $catalogService->type === ServiceType::License ? trim((string) ($fields['license_key'] ?? '')) : '';

            $items[(int) $catalogServiceId] = ['details' => $details, 'license_key' => $licenseKey === '' ? null : $licenseKey];
        }

        return $items;
    }
}
