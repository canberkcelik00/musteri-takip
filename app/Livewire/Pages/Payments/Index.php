<?php

namespace App\Livewire\Pages\Payments;

use App\Livewire\Concerns\WithTable;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The payment list (docs/UI.md §5): newest first. Customer, date and amount always show; the payment method shows
 * from `md` and the note from `lg`. The amount opens the payment's detail window at every width, where "Sil" is the
 * last, separate, confirmed action; below `md` the row has no "Sil" of its own. A click on an amount never deletes.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithTable;

    #[Url(except: '')]
    public string $customer = '';

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return [];
    }

    public function updatedCustomer(): void
    {
        $this->resetPage();
    }

    /**
     * A payment was recorded or deleted in a window: the list is read again.
     */
    #[On('payments-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    private function payments(): LengthAwarePaginator
    {
        $query = Payment::query()->with(['customer', 'paymentMethod']);

        if (ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->whereHas('customer', fn ($customers) => $customers->where('short_name', 'like', "%{$term}%"));
        }

        return $query->orderByDesc('paid_on')->orderByDesc('id')->paginate($this->perPage());
    }

    public function render(): View
    {
        $payments = $this->payments();

        return view('livewire.pages.payments.index', [
            'payments' => $payments,
            'customers' => Customer::query()->orderBy('short_name')->pluck('short_name', 'id')->all(),
        ])->title(__('payments.plural'));
    }
}
