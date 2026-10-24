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
        <div style="grid-column: 1 / -1"><dt>{{ __('payments.note') }}</dt><dd>{{ filled($payment->note) ? $payment->note : __('money.empty') }}</dd></div>
        @if ($creditText)
            <div style="grid-column: 1 / -1"><dd>{{ $creditText }}</dd></div>
        @endif
    </dl>

    @if ($payment->allocations->isNotEmpty())
        <details style="margin-top: 14px">
            <summary class="ui-label" style="cursor: pointer">{{ __('payments.allocations_heading', ['count' => $payment->allocations->count()]) }}</summary>
            <table class="ui-table" style="margin-top: 6px">
                <thead>
                    <tr>
                        <th>{{ __('payments.allocation_charge') }}</th>
                        <th class="is-end">{{ __('payments.amount') }}</th>
                        <th>{{ __('payments.allocation_source') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->charge?->description ?? __('payments.allocation_fallback_description') }}</td>
                            <td class="is-end">{{ (new Money($allocation->amount, $payment->currency))->format() }}</td>
                            <td>{{ $allocation->source->getLabel() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

    <div class="ui-actions-section" style="margin-top: 14px">
        <h3>{{ __('payments.actions_section') }}</h3>
        <div class="ui-actions-row">
            <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-payment', 'id' => $payment->id]) !!}">{{ __('payments.delete') }}</x-ui.button>
        </div>
    </div>

    <x-slot:footer>
        <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
    </x-slot:footer>
</x-ui.window>
