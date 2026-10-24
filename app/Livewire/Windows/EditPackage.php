<?php

namespace App\Livewire\Windows;

use App\Enums\Currency;
use App\Models\CatalogService;
use App\Models\Package;
use App\Support\Money;
use App\Support\Ui\MoneyAmountRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a package: a name, at least two catalog services and its own price (independent of their sum). The
 * services offered are the ones not archived plus the archived ones the package already holds, so saving never drops
 * them silently. Archive and delete sit in a named section, as in the other catalog window.
 */
class EditPackage extends Component
{
    #[Locked]
    public ?int $packageId = null;

    public string $name = '';

    public string $price = '';

    public string $currency = 'TRY';

    public string $notes = '';

    /** @var list<string> */
    public array $serviceIds = [];

    public function mount(?int $package = null): void
    {
        if ($package === null) {
            return;
        }

        $record = Package::query()->with('catalogServices:id')->findOrFail($package);

        $this->packageId = $record->id;
        $this->name = $record->name;
        $this->price = Money::formatAmount($record->price);
        $this->currency = $record->currency->value;
        $this->notes = (string) $record->notes;
        $this->serviceIds = $record->catalogServices->map(fn (CatalogService $service): string => (string) $service->id)->all();
    }

    public function save(): void
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'price' => (int) MoneyAmountRule::minorUnits($this->price),
            'currency' => Currency::from($this->currency),
            'notes' => trim($this->notes) === '' ? null : trim($this->notes),
        ];

        DB::transaction(function () use ($attributes): void {
            $package = $this->packageId === null
                ? Package::query()->create($attributes)
                : tap(Package::query()->findOrFail($this->packageId))->update($attributes);

            $package->catalogServices()->sync(array_map('intval', $this->serviceIds));
        });

        $this->dispatch('catalog-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('packages.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', new MoneyAmountRule],
            'currency' => ['required', Rule::enum(Currency::class)],
            'notes' => ['nullable', 'string'],
            'serviceIds' => ['required', 'array', 'min:2'],
            'serviceIds.*' => ['integer', Rule::in(array_keys($this->selectableServices()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('packages.name'),
            'price' => __('packages.price'),
            'currency' => __('money.currency'),
            'notes' => __('packages.notes'),
            'serviceIds' => __('packages.catalog_services'),
        ];
    }

    /**
     * Not archived catalog services, plus the archived ones the package already contains.
     *
     * @return array<int, string>
     */
    private function selectableServices(): array
    {
        $contained = $this->packageId === null
            ? CatalogService::query()->whereRaw('1 = 0')->select('catalog_services.id')
            : Package::query()->findOrFail($this->packageId)->catalogServices()->select('catalog_services.id');

        return CatalogService::query()
            ->where(function (Builder $query) use ($contained): void {
                $query->notArchived()->orWhereIn('catalog_services.id', $contained);
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (CatalogService $service): array => [
                $service->id => $service->isArchived() ? __('packages.archived_option', ['name' => $service->name]) : $service->name,
            ])
            ->all();
    }

    public function render(): View
    {
        $record = $this->packageId === null ? null : Package::query()->find($this->packageId);

        return view('livewire.windows.edit-package', [
            'record' => $record,
            'inUse' => $record?->isInUse() ?? false,
            'services' => $this->selectableServices(),
            'currencies' => collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
        ]);
    }
}
