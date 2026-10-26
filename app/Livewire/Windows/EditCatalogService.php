<?php

namespace App\Livewire\Windows;

use App\Enums\Currency;
use App\Enums\ServiceType;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CatalogService;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a catalog service (docs/UI.md §5 Katalog). The type decides which details are asked; the model keeps
 * only the keys of the current type. On an existing service the rarer actions sit in a named section: archive or
 * unarchive and, red and confirmed, delete (only while nothing uses the service).
 */
class EditCatalogService extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public ?int $serviceId = null;

    public string $type = '';

    public string $name = '';

    public string $price = '';

    public string $currency = 'TRY';

    public string $notes = '';

    /** @var array<string, string> */
    public array $details = [];

    public function mount(?int $service = null): void
    {
        $this->type = ServiceType::Hosting->value;

        if ($service === null) {
            return;
        }

        $record = CatalogService::query()->findOrFail($service);

        $this->serviceId = $record->id;
        $this->type = $record->type->value;
        $this->name = $record->name;
        $this->price = Money::formatAmount($record->price);
        $this->currency = $record->currency->value;
        $this->notes = (string) $record->notes;
        $this->details = array_map(fn (mixed $value): string => (string) $value, $record->details ?? []);
    }

    public function save(): void
    {
        $this->validate();

        $type = ServiceType::from($this->type);

        $attributes = [
            'type' => $type,
            'name' => $this->name,
            'price' => (int) MoneyAmountRule::minorUnits($this->price),
            'currency' => Currency::from($this->currency),
            'notes' => trim($this->notes) === '' ? null : trim($this->notes),
            'details' => $type->onlyCatalogDetails($this->details),
        ];

        if ($this->serviceId === null) {
            CatalogService::query()->create($attributes);
        } else {
            CatalogService::query()->findOrFail($this->serviceId)->update($attributes);
        }

        $this->dispatch('catalog-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('catalog_services.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::enum(ServiceType::class)],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', new MoneyAmountRule],
            'currency' => ['required', Rule::enum(Currency::class)],
            'notes' => ['nullable', 'string'],
        ];

        foreach (ServiceType::tryFrom($this->type)?->catalogDetailKeys() ?? [] as $key) {
            $rules["details.{$key}"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $type = ServiceType::tryFrom($this->type);
        $attributes = [
            'type' => __('catalog_services.type'),
            'name' => __('catalog_services.name'),
            'price' => __('catalog_services.price'),
            'currency' => __('money.currency'),
            'notes' => __('catalog_services.notes'),
        ];

        if ($type !== null) {
            foreach ($type->catalogDetailKeys() as $key) {
                $attributes["details.{$key}"] = $type->catalogDetailLabel($key);
            }
        }

        return $attributes;
    }

    public function render(): View
    {
        $record = $this->serviceId === null ? null : CatalogService::query()->find($this->serviceId);
        $type = ServiceType::tryFrom($this->type);

        return view('livewire.windows.edit-catalog-service', [
            'record' => $record,
            'inUse' => $record?->isInUse() ?? false,
            'types' => collect(ServiceType::cases())->mapWithKeys(fn (ServiceType $case): array => [$case->value => $case->getLabel()])->all(),
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
            'detailLabels' => $type === null ? [] : collect($type->catalogDetailKeys())->mapWithKeys(fn (string $key): array => [$key => $type->catalogDetailLabel($key)])->all(),
        ]);
    }
}
