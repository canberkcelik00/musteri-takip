<?php

namespace App\Livewire\Windows;

use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds or renames a payment method. On an existing one the rarer actions sit in a named section: archive (never the
 * last active method) or unarchive and, red and confirmed, delete (only while no payment uses it).
 */
class EditPaymentMethod extends Component
{
    #[Locked]
    public ?int $methodId = null;

    public string $name = '';

    public function mount(?int $method = null): void
    {
        if ($method === null) {
            return;
        }

        $record = PaymentMethod::query()->findOrFail($method);

        $this->methodId = $record->id;
        $this->name = $record->name;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->methodId === null) {
            PaymentMethod::query()->create(['name' => $this->name]);
        } else {
            PaymentMethod::query()->findOrFail($this->methodId)->update(['name' => $this->name]);
        }

        $this->dispatch('payment-methods-changed');
        $this->dispatch('close-window');
        $this->dispatch('notify', message: __('payment_methods.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['name' => __('payment_methods.name')];
    }

    public function render(): View
    {
        $record = $this->methodId === null ? null : PaymentMethod::query()->find($this->methodId);

        return view('livewire.windows.edit-payment-method', [
            'record' => $record,
            'inUse' => $record?->isInUse() ?? false,
        ]);
    }
}
