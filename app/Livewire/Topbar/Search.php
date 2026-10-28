<?php

namespace App\Livewire\Topbar;

use App\Models\Customer;
use App\Support\Dns\HostName;
use App\Support\Ui\Navigation;
use App\Support\Ui\RecentCustomers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The top bar's search (Ctrl+K, docs/UI.md §2 Kural 12, §3): customers by short name, legal title, contact or
 * domain name or phone number (Customer::search). A result opens the customer's card; its "Ödeme al" opens the
 * payment window with the customer chosen (for a user without money access, "İş ekle" opens the task window).
 * An empty box lists the customers last opened, and a term that starts a menu name adds a "Git: …" row. Catalog,
 * packages and payment methods stay in their own lists. A term that is a domain name starts the results with
 * "Alan adını kontrol et: …", which opens the check window (Faz 16).
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

    /**
     * The customers last opened in this session, newest first; archived ones are left out. One query, so the domain
     * names (which need the services) are not shown here.
     *
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function recents(): Collection
    {
        $ids = RecentCustomers::ids();

        if ($ids === []) {
            return new Collection;
        }

        return Customer::query()
            ->select(['id', 'short_name', 'legal_title', 'archived_at'])
            ->notArchived()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Customer $customer): int|false => array_search($customer->id, $ids, true))
            ->values();
    }

    /**
     * "Git: İşler" rows for the pages whose menu name starts with the term.
     *
     * @return list<array{label: string, url: string}>
     */
    #[Computed]
    public function pages(): array
    {
        return mb_strlen(trim($this->query)) < self::MIN_LENGTH ? [] : Navigation::matching($this->query);
    }

    /**
     * The term as a domain name for the "Alan adını kontrol et" row; null when it is not one (a bare word such as a
     * customer name has no dot) or the user may not look at services.
     */
    #[Computed]
    public function domain(): ?string
    {
        $term = trim($this->query);

        if (! str_contains($term, '.') || ! auth()->user()?->can('services.view')) {
            return null;
        }

        return HostName::normalize($term);
    }

    public function render(): View
    {
        return view('livewire.topbar.search');
    }
}
