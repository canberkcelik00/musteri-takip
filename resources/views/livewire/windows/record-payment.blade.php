@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;
@endphp

<x-ui.window :title="__('payments.record_heading')" submit="save">
    <div class="ui-form">
        @unless ($fixed)
            <div class="is-full">
                <x-ui.select :label="__('payments.customer')" wire:model.live="customer_id" :options="$customers" :searchable="true" />
            </div>
        @endunless

        <x-ui.money-input :label="__('payments.amount')" wire:model.live.blur="amount" :suffix="$currencyLabel" autofocus />
        <x-ui.select :label="__('money.currency')" wire:model.live="currency" :options="$currencies" />
        <x-ui.date-input :label="__('payments.paid_on')" wire:model="paid_on" />
        <x-ui.select :label="__('payments.payment_method')" wire:model="payment_method_id" :options="$methods" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('payments.note')" wire:model="note" /></div>

        <div class="is-full">
            <x-ui.toggle wire:model.live="show_allocations" :label="__('payments.show_allocations')" />
        </div>

        @if ($show_allocations)
            <div class="is-full flex flex-col gap-3">
                @if ($customer === null)
                    <p class="ui-note">{{ __('ui.payment.choose_customer') }}</p>
                @elseif ($charges->isEmpty())
                    <p class="ui-note">{{ __('payments.no_open_charges') }}</p>
                @else
                    <p class="ui-hint">{{ __('payments.allocations_helper') }}</p>
                    @foreach ($charges as $charge)
                        <x-ui.money-input
                            wire:key="allocation-{{ $charge->id }}"
                            wire:model.live.blur="allocations.{{ $charge->id }}"
                            :suffix="$currencyLabel"
                            :label="__('payments.allocation_label', [
                                'description' => $charge->description ?? __('payments.allocation_fallback_description'),
                                'due_date' => Carbon::parse($charge->due_date)->format('d.m.Y'),
                                'remaining' => (new Money($charge->remainingAmount(), $charge->currency))->format(),
                            ])"
                        />
                    @endforeach
                    @if ($summary !== '')
                        <p class="ui-note">{{ $summary }}</p>
                    @endif
                @endif
            </div>
        @endif
    </div>
</x-ui.window>
