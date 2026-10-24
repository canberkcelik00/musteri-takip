<?php

namespace App\Livewire\Pages\Customers;

use App\Enums\ChargeStatus;
use App\Enums\CustomerServiceStatus;
use App\Livewire\Concerns\WithTable;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The customer list (docs/UI.md §5): customers with an overdue charge first, then by short name. The row's only
 * action is "Ödeme al"; the row opens the customer's card. Summaries are loaded with the page, never per row.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithTable;

    public const string ARCHIVE_ACTIVE = 'active';

    public const string ARCHIVE_ARCHIVED = 'archived';

    public const string ARCHIVE_ALL = 'all';

    /** Archived customers are hidden unless asked for. */
    #[Url(except: self::ARCHIVE_ACTIVE)]
    public string $archive = self::ARCHIVE_ACTIVE;

    #[Url(except: false)]
    public bool $renewalSoon = false;

    #[Url(except: false)]
    public bool $awaitingFreeDecision = false;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['short_name', 'next_renewal_on', 'active_services_count'];
    }

    public function updatedArchive(): void
    {
        $this->resetPage();
    }

    public function updatedRenewalSoon(): void
    {
        $this->resetPage();
    }

    public function updatedAwaitingFreeDecision(): void
    {
        $this->resetPage();
    }

    public function toggleRenewalSoon(): void
    {
        $this->renewalSoon = ! $this->renewalSoon;
        $this->resetPage();
    }

    public function toggleAwaitingFreeDecision(): void
    {
        $this->awaitingFreeDecision = ! $this->awaitingFreeDecision;
        $this->resetPage();
    }

    /**
     * A payment or a customer was saved in a window: the list is read again.
     */
    #[On('payments-changed')]
    #[On('customers-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    private function customers(): LengthAwarePaginator
    {
        $query = $this->withSummaries(Customer::query());

        if (trim($this->search) !== '') {
            $query->search($this->search);
        }

        match ($this->archive) {
            self::ARCHIVE_ARCHIVED => $query->onlyArchived(),
            self::ARCHIVE_ALL => null,
            default => $query->notArchived(),
        };

        if ($this->renewalSoon) {
            $query->renewalSoon();
        }

        if ($this->awaitingFreeDecision) {
            $query->awaitingFreeDecision();
        }

        $sort = $this->requestedSort();

        if ($sort !== null) {
            $query->orderBy($sort[0], $sort[1]);
        } else {
            $query->orderByDesc('has_overdue');
        }

        return $query->orderBy('short_name')->orderBy('id')->paginate($this->perPage());
    }

    /**
     * Summary values of the list, loaded with the customers so the list does not query per row: active service
     * count, nearest end date of the active services (free ones included), the open charges with their allocated
     * amount, and whether any open charge is overdue (for the sort order and the open debt's color).
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function withSummaries(Builder $query): Builder
    {
        $activeServices = fn (Builder $services): Builder => $services->where('status', CustomerServiceStatus::Active);

        return $query
            ->withCount(['customerServices as active_services_count' => $activeServices])
            ->withMin(['customerServices as next_renewal_on' => $activeServices], 'end_date')
            ->withExists(['charges as has_overdue' => fn (Builder $charges): Builder => $charges->scopes(['overdue'])])
            ->with(['charges' => fn ($charges) => $charges
                ->where('status', ChargeStatus::Open)
                ->withSum('allocations', 'amount')]);
    }

    public function render(): View
    {
        $customers = $this->customers();

        return view('livewire.pages.customers.index', [
            'customers' => $customers,
            'total' => $customers->total(),
        ])->title(__('customers.plural'));
    }
}
