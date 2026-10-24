@php
    use App\Support\Ui\Js;
    use App\Support\Ui\Links;
    use Illuminate\Support\Carbon;

    $columns = [
        ['key' => 'customer', 'label' => __('payments.customer')],
        ['key' => 'paid_on', 'label' => __('payments.paid_on')],
        ['key' => 'amount', 'label' => __('payments.amount')],
        ['key' => 'method', 'label' => __('payments.payment_method'), 'from' => 'md'],
        ['key' => 'note', 'label' => __('payments.note'), 'from' => 'lg'],
    ];
    $filtered = trim($search) !== '' || $customer !== '';
@endphp

<div>
    <h1 class="ui-title">{{ __('payments.plural') }}</h1>
    <p class="ui-subtitle">{{ __('payments.count', ['count' => $payments->total()]) }}</p>

    <x-ui.table class="mt-5" :columns="$columns" :end="true" :empty="$payments->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <div style="min-width: 200px"><x-ui.select wire:model.live="customer" :options="['' => __('archive.filter_all')] + $customers" :searchable="true" :aria-label="__('payments.customer')" /></div>
        </x-slot:toolbar>

        @foreach ($payments as $payment)
            <x-ui.row wire:key="payment-{{ $payment->id }}">
                <td>
                    <div class="ui-cell-identity">
                        <x-ui.avatar :name="$payment->customer->short_name" :id="$payment->customer_id" />
                        <a class="ui-cell-title" href="{{ Links::customer($payment->customer) }}">{{ $payment->customer->short_name }}</a>
                    </div>
                </td>
                <td style="white-space: nowrap">{{ Carbon::parse($payment->paid_on)->format('d.m.Y') }}</td>
                <td>
                    <button type="button" class="ui-link-button ui-cell-title" x-on:click="{!! Js::openWindow('view-payment', ['payment' => $payment->id]) !!}"><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /></button>
                </td>
                <td class="ui-from-md">{{ $payment->paymentMethod?->name }}</td>
                <td class="ui-from-lg">{{ filled($payment->note) ? $payment->note : __('money.empty') }}</td>
                <x-slot:actions>
                    <x-ui.button variant="danger" size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-payment', 'id' => $payment->id]) !!}">{{ __('payments.delete') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('payments.empty_heading')" :description="__('payments.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('record-payment') !!}">{{ __('payments.record') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $payments->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
