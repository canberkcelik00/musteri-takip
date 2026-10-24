<?php

namespace App\Livewire\Pages\Quotes;

use App\Enums\QuoteDisplayStatus;
use App\Enums\QuoteStatus;
use App\Livewire\Concerns\WithTable;
use App\Models\Quote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The quote list (docs/UI.md §5 Teklifler), newest first. Number, total and state always show, the validity from
 * `md`. The number opens the quote's window at every width; the row has at most two labelled buttons from `md`: a
 * draft "Düzenle" + "Gönder", a sent quote that is still valid "Kabul et" + "Reddet", an expired one "Tekrar
 * gönder", a decided one none. The state is derived on every render (Quote::displayStatus).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithTable;

    public const string STATUS_ALL = 'all';

    #[Url(except: 'all')]
    public string $status = self::STATUS_ALL;

    /**
     * @return list<string>
     */
    protected function sortableColumns(): array
    {
        return ['valid_until'];
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * A quote changed in a window: the list is read again.
     */
    #[On('quotes-changed')]
    public function refresh(): void
    {
        // Livewire re-renders the component after any listener.
    }

    /**
     * @return LengthAwarePaginator<int, Quote>
     */
    private function quotes(): LengthAwarePaginator
    {
        $query = Quote::query()->with(['customer', 'items']);

        $this->filterByStatus($query);

        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('number', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhere('recipient_company', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('short_name', 'like', $like));
            });
        }

        $sort = $this->requestedSort();

        if ($sort !== null) {
            $query->orderBy($sort[0], $sort[1]);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($this->perPage());
    }

    /**
     * The state filter works on the displayed state: "Süresi doldu" is a sent quote past its validity, "Gönderildi"
     * one that is still valid.
     *
     * @param  Builder<Quote>  $query
     */
    private function filterByStatus(Builder $query): void
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        match (QuoteDisplayStatus::tryFrom($this->status)) {
            QuoteDisplayStatus::Expired => $query->where('status', QuoteStatus::Sent)->where('valid_until', '<', $today),
            QuoteDisplayStatus::Sent => $query->where('status', QuoteStatus::Sent)->where('valid_until', '>=', $today),
            QuoteDisplayStatus::Draft => $query->where('status', QuoteStatus::Draft),
            QuoteDisplayStatus::Accepted => $query->where('status', QuoteStatus::Accepted),
            QuoteDisplayStatus::Rejected => $query->where('status', QuoteStatus::Rejected),
            null => null,
        };
    }

    public function render(): View
    {
        $quotes = $this->quotes();

        return view('livewire.pages.quotes.index', [
            'quotes' => $quotes,
            'statuses' => [self::STATUS_ALL => __('archive.filter_all')] + collect(QuoteDisplayStatus::cases())->mapWithKeys(fn (QuoteDisplayStatus $case): array => [$case->value => $case->getLabel()])->all(),
        ])->title(__('quotes.plural'));
    }
}
