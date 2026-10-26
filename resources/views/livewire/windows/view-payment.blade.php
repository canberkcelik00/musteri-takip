@php
    use App\Support\Money;
    use App\Support\Ui\Js;
    use Illuminate\Support\Carbon;
@endphp

<x-ui.window :title="__('payments.detail_heading')">
    <dl class="ui-dl-form">
        <div><dt>{{ __('payments.paid_on') }}</dt><dd>{{ Carbon::parse($payment->paid_on)->format('d.m.Y') }}</dd></div>
        <div><dt>{{ __('payments.customer') }}</dt><dd>{{ $payment->customer->short_name }}</dd></div>
        <div><dt>{{ __('payments.amount') }}</dt><dd><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /></dd></div>
        <div><dt>{{ __('payments.payment_method') }}</dt><dd>{{ $payment->paymentMethod?->name }}</dd></div>
        @if ($payment->recordedBy)
            <div><dt>{{ __('payments.recorded_by') }}</dt><dd>{{ $payment->recordedBy->name }}</dd></div>
        @endif
        <div class="is-full"><dt>{{ __('payments.note') }}</dt><dd>{{ filled($payment->note) ? $payment->note : __('money.empty') }}</dd></div>
        @if ($creditText)
            <div class="is-full"><dd>{{ $creditText }}</dd></div>
        @endif
    </dl>

    @if ($payment->allocations->isNotEmpty())
        <details class="ui-mt-3">
            <summary class="ui-label ui-summary">{{ __('payments.allocations_heading', ['count' => $payment->allocations->count()]) }}</summary>
            <x-ui.table class="ui-mt-2" :card="false" :columns="[
                ['key' => 'charge', 'label' => __('payments.allocation_charge')],
                ['key' => 'amount', 'label' => __('payments.amount'), 'end' => true],
                ['key' => 'source', 'label' => __('payments.allocation_source')],
            ]">
                @foreach ($payment->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->charge?->description ?? __('payments.allocation_fallback_description') }}</td>
                        <td class="is-end">{{ (new Money($allocation->amount, $payment->currency))->format() }}</td>
                        <td>{{ $allocation->source->getLabel() }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </details>
    @endif

    <div class="ui-actions-section ui-mt-3">
        <h3>{{ __('payments.actions_section') }}</h3>
        <div class="ui-actions-row">
            <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-payment', 'id' => $payment->id]) !!}">{{ __('payments.delete') }}</x-ui.button>
        </div>
    </div>

    <x-slot:footer>
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
