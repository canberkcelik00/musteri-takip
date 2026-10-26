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

    /** A shorter term matches nearly everything: no query runs and no result box opens. */
    public const int MIN_LENGTH = 2;

    public string $query = '';

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->query)) < self::MIN_LENGTH) {
            return new Collection;
        }

        return Customer::query()
            ->select(['id', 'short_name', 'legal_title', 'archived_at'])
            ->with(['customerServices:id,customer_id', 'customerServices.items:id,customer_service_id,details'])
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
