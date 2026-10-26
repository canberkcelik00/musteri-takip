@php
    use App\Enums\QuoteStatus;
    use App\Support\Money;
    use App\Support\Ui\Js;
    use App\Support\Ui\StatusBadges;
    use Illuminate\Support\Carbon;

    $columns = [
        ['key' => 'number', 'label' => __('quotes.column.number')],
        ['key' => 'total', 'label' => __('quotes.column.total'), 'end' => true],
        ['key' => 'valid_until', 'label' => __('quotes.column.valid_until'), 'from' => 'md', 'sortable' => true],
        ['key' => 'status', 'label' => __('quotes.column.status')],
    ];
    $filtered = trim($search) !== '' || $status !== 'all';
@endphp

<div>
    <x-ui.page-header :title="__('quotes.plural')">
        <x-ui.button x-on:click="{!! Js::openWindow('create-quote') !!}">{{ __('quotes.new') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table :count="__('quotes.count', ['count' => $quotes->total()])" class="mt-5" :columns="$columns" :sort="$sort" :direction="$direction" :end="true" :empty="$quotes->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.select width="md" wire:model.live="status" :options="$statuses" :aria-label="__('quotes.filter_status')" />
        </x-slot:toolbar>

        @foreach ($quotes as $quote)
            @php
                $tone = StatusBadges::quoteTone($quote);
                $draft = $quote->status === QuoteStatus::Draft;
                $expired = $quote->isExpired();
                $sentAndValid = $quote->status === QuoteStatus::Sent && ! $expired;
                $recipient = $quote->customer !== null
                    ? $quote->customer->short_name
                    : implode(' · ', array_filter([$quote->recipient_company, $quote->recipient_name]));
            @endphp
            <x-ui.row wire:key="quote-{{ $quote->id }}">
                <td>
                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('quote', ['quote' => $quote->id]) !!}">{{ $quote->number }}</x-ui.link-button>
                    <span class="ui-sub-line">{{ $recipient }}</span>
                </td>
                <td class="is-end">
                    @forelse ($quote->totals() as $money)
                        <div><x-ui.money :amount="$money->amount" :currency="$money->currency" /></div>
                    @empty
                        {{ __('quotes.pdf.free') }}
                    @endforelse
                </td>
                <td class="ui-from-md">{{ Carbon::parse($quote->valid_until)->format('d.m.Y') }}</td>
                <td>
                    @if ($tone)
                        <x-ui.badge :tone="$tone">{{ StatusBadges::quoteLabel($quote) }}</x-ui.badge>
                    @else
                        {{ StatusBadges::quoteLabel($quote) }}
                    @endif
                </td>
                <x-slot:actions>
                    @if ($draft)
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('quote', ['quote' => $quote->id]) !!}">{{ __('quotes.edit') }}</x-ui.button>
                    @endif
                    @if ($draft || $expired)
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('send-quote', ['quote' => $quote->id]) !!}">{{ $draft ? __('quotes.send') : __('quotes.resend') }}</x-ui.button>
                    @endif
                    @if ($sentAndValid)
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('accept-quote', ['quote' => $quote->id]) !!}">{{ __('quotes.accept') }}</x-ui.button>
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('confirm', ['action' => 'reject-quote', 'id' => $quote->id]) !!}">{{ __('quotes.reject') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('quotes.empty_heading')" :description="__('quotes.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('create-quote') !!}">{{ __('quotes.new') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $quotes->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
