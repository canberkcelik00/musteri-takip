<?php

namespace App\Livewire\Windows;

use App\Actions\Contacts\CreateContact;
use App\Actions\Contacts\MakeContactPrimary;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Contact as ContactModel;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or edits a contact of a customer (docs/UI.md §5). On an existing contact the rarer actions sit in a named
 * section: "Birincil yap" and, red and confirmed, "Sil" — both only for a contact that is not the primary one.
 * Every change goes through the Actions in App\Actions\Contacts, which keep exactly one primary contact.
 */
class Contact extends Component
{
    use AuthorizesPermission;

    #[Locked]
    public int $customerId = 0;

    #[Locked]
    public ?int $contactId = null;

    #[Locked]
    public bool $isPrimary = false;

    public string $name = '';

    public string $title = '';

    public string $phone = '';

    public string $email = '';

    public bool $make_primary = false;

    public function mount(int $customer, ?int $contact = null): void
    {
        $this->customerId = Customer::query()->findOrFail($customer)->id;

        if ($contact === null) {
            return;
        }

        $record = ContactModel::query()->where('customer_id', $this->customerId)->findOrFail($contact);

        $this->contactId = $record->id;
        $this->isPrimary = (bool) $record->is_primary;
        $this->name = $record->name;
        $this->title = (string) $record->title;
        $this->phone = (string) $record->phone;
        $this->email = (string) $record->email;
    }

    public function save(CreateContact $createContact): void
    {
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'title' => $this->nullIfBlank($this->title),
            'phone' => $this->nullIfBlank($this->phone),
            'email' => $this->nullIfBlank($this->email),
        ];

        if ($this->contactId === null) {
            $createContact->handle(Customer::query()->findOrFail($this->customerId), $attributes, $this->make_primary);
        } else {
            ContactModel::query()->where('customer_id', $this->customerId)->findOrFail($this->contactId)->update($attributes);
        }

        $this->dispatch('contacts-changed');
        $this->dispatch('close-window');
    }

    public function makePrimary(MakeContactPrimary $makeContactPrimary): void
    {
        if ($this->contactId === null || $this->isPrimary) {
            return;
        }

        $makeContactPrimary->handle(ContactModel::query()->where('customer_id', $this->customerId)->findOrFail($this->contactId));

        $this->dispatch('contacts-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('contacts.made_primary'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'make_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('contacts.name'),
            'title' => __('contacts.title'),
            'phone' => __('contacts.phone'),
            'email' => __('contacts.email'),
        ];
    }

    public function render(): View
    {
        return view('livewire.windows.contact');
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
