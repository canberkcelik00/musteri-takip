<?php

namespace App\Livewire\Pages\Charges;

use App\Actions\Charges\SumRemainingByCurrency;
use App\Enums\ChargeStatus;
use App\Enums\Currency;
use App\Livewire\Concerns\WithTable;
use App\Models\Charge;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The charge list (docs/UI.md §5 "Açık borçlar listesi"): open charges by default, oldest due date first, with a
 * total row per currency under "Kalan" that follows the search and the filters. The state is a column from `lg`
 * and a badge under "Kalan" below it; the row has "Ödeme al" and (from `md`) "Düzenle".
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithTable;

    public const string STATUS_ALL = 'all';

    /** One of the charge statuses, or "all"; open charges unless asked otherwise. */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: false)]
    public bool $onlyOverdue = false;

    #[Url(except: false)]
    public bool $dueThisMonth = false;

    #[Url(except: false)]
    public bool $dueSoon = false;

    #[Url(except: false)]
    public bool $hideArchived = false;

    #[Url(except: '')]
    public string $currency = '';

    #[Url(except: '')]
    public string $customer = '';

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['due_date'];
    }

    public function updated(string $name): void
    {
        if ($name !== 'search' && ! str_starts_with($name, 'paginators')) {
            $this->resetPage();
        }
    }

    public function toggle(string $filter): void
    {
        if (in_array($filter, ['onlyOverdue', 'dueThisMonth', 'dueSoon', 'hideArchived'], true)) {
            $this->{$filter} = ! $this->{$filter};
            $this->resetPage();
        }
    }

    /**
     * A payment or a charge was changed in a window: the list is read again.
     */
    #[On('payments-changed')]
    #[On('charges-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * The filtered, searched charges (not yet limited to a page).
     *
     * @return Builder<Charge>
     */
    private function filtered(): Builder
    {
        $query = Charge::query()->with('customer');

        if (in_array($this->status, array_column(ChargeStatus::cases(), 'value'), true)) {
            $query->where('status', $this->status);
        }

        if ($this->onlyOverdue) {
            $query->overdue();
        }

        if ($this->dueThisMonth) {
            $query->dueThisMonth();
        }

        if ($this->dueSoon) {
            $query->dueSoon();
        }

        if ($this->hideArchived) {
            $query->unarchivedCustomer();
        }

        if (Currency::tryFrom($this->currency) !== null) {
            $query->where('currency', $this->currency);
        }

        if (ctype_digit($this->customer)) {
            $query->where('customer_id', (int) $this->customer);
        }

        $term = trim($this->search);

        if ($term !== '') {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('description', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customers) => $customers->where('short_name', 'like', "%{$term}%"));
            });
        }

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, Charge>
     */
    private function charges(): LengthAwarePaginator
    {
        $query = $this->filtered()->withSum('allocations', 'amount');
        $sort = $this->requestedSort();

        $query->orderBy('due_date', $sort !== null ? $sort[1] : 'asc')->orderBy('id');

        return $query->paginate($this->perPage());
    }

    /**
     * The remaining open debt of the whole filtered list, one total per currency (never added across currencies);
     * nothing to show (a list of paid or cancelled charges) gives none.
     *
     * @return array<string, Money>
     */
    private function totals(): array
    {
        return app(SumRemainingByCurrency::class)->handle($this->filtered()->where('status', ChargeStatus::Open));
    }

    public function render(): View
    {
        $charges = $this->charges();

        return view('livewire.pages.charges.index', [
            'charges' => $charges,
            'totals' => $this->totals(),
            'customers' => Customer::query()->orderBy('short_name')->pluck('short_name', 'id')->all(),
            'statuses' => [self::STATUS_ALL => __('archive.filter_all')] + collect(ChargeStatus::cases())->mapWithKeys(fn (ChargeStatus $status): array => [$status->value => $status->getLabel()])->all(),
            'currencies' => ['' => __('archive.filter_all')] + collect(Currency::cases())->mapWithKeys(fn (Currency $case): array => [$case->value => $case->getLabel()])->all(),
        ])->title(__('navigation.open_charges'));
    }
}
