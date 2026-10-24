<?php

namespace App\Livewire\Windows;

use App\Actions\Customers\CreateCustomer as CreateCustomerAction;
use App\Support\Ui\Links;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The new-customer window: the customer's details and the first (primary) contact, created together through the
 * CreateCustomer Action; afterwards the customer's card opens.
 */
class CreateCustomer extends Component
{
    public string $short_name = '';

    public string $legal_title = '';

    public string $tax_office = '';

    public string $tax_number = '';

    public string $billing_address = '';

    public string $notes = '';

    public string $contact_name = '';

    public string $contact_title = '';

    public string $contact_phone = '';

    public string $contact_email = '';

    public function save(CreateCustomerAction $createCustomer): void
    {
        $this->validate();

        $customer = $createCustomer->handle(
            [
                'short_name' => $this->short_name,
                'legal_title' => $this->nullIfBlank($this->legal_title),
                'tax_office' => $this->nullIfBlank($this->tax_office),
                'tax_number' => $this->nullIfBlank($this->tax_number),
                'billing_address' => $this->nullIfBlank($this->billing_address),
                'notes' => $this->nullIfBlank($this->notes),
            ],
            [
                'name' => $this->contact_name,
                'title' => $this->nullIfBlank($this->contact_title),
                'phone' => $this->nullIfBlank($this->contact_phone),
                'email' => $this->nullIfBlank($this->contact_email),
            ],
        );

        $this->dispatch('customers-changed');
        $this->dispatch('notify', message: __('customers.created'));

        $this->redirect(Links::customer($customer));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'short_name' => ['required', 'string', 'max:255', Rule::unique('customers', 'short_name')],
            'legal_title' => ['nullable', 'string', 'max:255'],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
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
            'contact_name' => __('contacts.name'),
            'contact_title' => __('contacts.title'),
            'contact_phone' => __('contacts.phone'),
            'contact_email' => __('contacts.email'),
        ];
    }

    public function render(): View
    {
        return view('livewire.windows.create-customer');
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
