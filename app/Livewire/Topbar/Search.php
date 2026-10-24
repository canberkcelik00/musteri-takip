<?php

namespace App\Livewire\Topbar;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The top bar's search (Ctrl+K, docs/UI.md §2 Kural 12, §3): customers by short name, legal title, contact or
 * domain name (Customer::search). A result opens the customer's card; its "Ödeme al" opens the payment window with
 * the customer chosen. Catalog, packages and payment methods stay in their own lists.
 */
class Search extends Component
{
    public const int LIMIT = 8;

    public string $query = '';

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function results(): Collection
    {
        if (trim($this->query) === '') {
            return new Collection;
        }

        return Customer::query()
            ->with('customerServices.items')
            ->search($this->query)
            ->orderBy('short_name')
            ->limit(self::LIMIT)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.topbar.search');
    }
}
