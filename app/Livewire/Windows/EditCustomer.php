<?php

namespace App\Livewire\Windows;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Customer;
use App\Rules\PcloudUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The customer's own details in the form window, opened from the card's billing information. Archive and delete stay
 * at the bottom of the card, apart from this form.
 */
class EditCustomer extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $customerId = 0;

    public string $short_name = '';

    public string $legal_title = '';

    public string $tax_office = '';

    public string $tax_number = '';

    public string $billing_address = '';

    public string $notes = '';

    public string $pcloud_url = '';

    public function mount(int $customer): void
    {
        $record = Customer::query()->findOrFail($customer);

        $this->customerId = $record->id;
        $this->short_name = $record->short_name;
        $this->legal_title = (string) $record->legal_title;
        $this->tax_office = (string) $record->tax_office;
        $this->tax_number = (string) $record->tax_number;
        $this->billing_address = (string) $record->billing_address;
        $this->notes = (string) $record->notes;
        $this->pcloud_url = (string) $record->pcloud_url;
    }

    public function save(): void
    {
        $this->validate();

        Customer::query()->findOrFail($this->customerId)->update([
            'short_name' => $this->short_name,
            'legal_title' => $this->nullIfBlank($this->legal_title),
            'tax_office' => $this->nullIfBlank($this->tax_office),
            'tax_number' => $this->nullIfBlank($this->tax_number),
            'billing_address' => $this->nullIfBlank($this->billing_address),
            'notes' => $this->nullIfBlank($this->notes),
            'pcloud_url' => $this->nullIfBlank($this->pcloud_url),
        ]);

        $this->dispatch('customers-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('customers.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'short_name' => ['required', 'string', 'max:255', Rule::unique('customers', 'short_name')->ignore($this->customerId)],
            'legal_title' => ['nullable', 'string', 'max:255'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'pcloud_url' => ['nullable', 'string', 'max:255', new PcloudUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'short_name' => __('customers.short_name'),
            'legal_title' => __('customers.legal_title'),
            'tax_office' => __('customers.tax_office'),
            'tax_number' => __('customers.tax_number'),
            'billing_address' => __('customers.billing_address'),
            'notes' => __('customers.notes'),
            'pcloud_url' => __('customers.pcloud_url'),
        ];
    }

    public function render(): View
    {
        return view('livewire.windows.edit-customer');
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
