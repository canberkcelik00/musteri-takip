@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="__('quotes.edit_heading', ['number' => $quote->number])" :submit="$draft ? 'save' : null">
    <div class="ui-form">
        <div class="is-full">
            <x-ui.select :label="__('quotes.customer')" wire:model.live="customer_id" :options="['' => '—'] + $customers" :searchable="true" :disabled="! $draft" />
            <span class="ui-hint">{{ __('quotes.customer_helper') }}</span>
        </div>

        @if ($customer_id === '')
            <x-ui.input :label="__('quotes.recipient_name')" wire:model="recipient_name" maxlength="255" :disabled="! $draft" />
            <x-ui.input :label="__('quotes.recipient_company')" wire:model="recipient_company" maxlength="255" :disabled="! $draft" />
            <x-ui.input type="email" :label="__('quotes.recipient_email')" wire:model="recipient_email" maxlength="255" :disabled="! $draft" />
            <x-ui.input type="tel" :label="__('quotes.recipient_phone')" wire:model="recipient_phone" maxlength="255" :disabled="! $draft" />
        @endif

        <div class="ui-field is-full">
            <span class="ui-label">{{ __('quotes.items') }}</span>
            @error('items') <span class="ui-error">{{ $message }}</span> @enderror

            @foreach ($items as $index => $row)
                <div class="ui-actions-section" wire:key="quote-item-{{ $index }}-{{ $row['id'] ?? 'new' }}" style="margin-top: 8px">
                    <div class="ui-form">
                        @if ($row['id'] !== null)
                            <div class="is-full"><strong>{{ $row['name'] }}</strong></div>
                        @else
                            <div class="is-full"><x-ui.select :label="__('quotes.item')" wire:model.live="items.{{ $index }}.item" :options="$catalog" :searchable="true" /></div>
                        @endif
                        <x-ui.money-input :label="__('quotes.price')" wire:model="items.{{ $index }}.price" :suffix="$currencies[$row['currency']] ?? ''" :disabled="! $draft" />
                        <x-ui.select :label="__('money.currency')" wire:model.live="items.{{ $index }}.currency" :options="$currencies" :disabled="! $draft" />
                        <div class="is-full"><x-ui.toggle wire:model.live="items.{{ $index }}.is_free" :label="__('quotes.is_free')" :disabled="! $draft" /></div>
                    </div>
                    @if ($draft)
                        <div class="ui-actions-row" style="margin-top: 8px">
                            <x-ui.button size="sm" wire:click="moveItem({{ $index }}, -1)" :disabled="$index === 0">{{ __('actions.up') }}</x-ui.button>
                            <x-ui.button size="sm" wire:click="moveItem({{ $index }}, 1)" :disabled="$index === count($items) - 1">{{ __('actions.down') }}</x-ui.button>
                            <x-ui.button size="sm" variant="danger" wire:click="removeItem({{ $index }})">{{ __('quotes.remove_item') }}</x-ui.button>
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($draft)
                <div><x-ui.button size="sm" wire:click="addItem">{{ __('quotes.add_item') }}</x-ui.button></div>
            @endif
        </div>

        <div class="is-full"><x-ui.input type="textarea" :label="__('quotes.notes')" wire:model="notes" :disabled="! $draft" /></div>

        <div class="ui-actions-section is-full">
            <h3>{{ __('quotes.actions_section') }}</h3>
            <div class="ui-actions-row">
                <x-ui.button :href="route('app.quotes.pdf', $quote)">{{ __('quotes.download') }}</x-ui.button>
                @if ($canSend)
                    <x-ui.button x-on:click="{!! Js::openWindow('send-quote', ['quote' => $quote->id]) !!}">{{ $resend ? __('quotes.resend') : __('quotes.send') }}</x-ui.button>
                @endif
                @if ($canDecide)
                    <x-ui.button x-on:click="{!! Js::openWindow('accept-quote', ['quote' => $quote->id]) !!}">{{ __('quotes.accept') }}</x-ui.button>
                    <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'reject-quote', 'id' => $quote->id]) !!}">{{ __('quotes.reject') }}</x-ui.button>
                @endif
            </div>
            @if ($draft)
                <div class="ui-actions-row">
                    <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-quote', 'id' => $quote->id]) !!}">{{ __('quotes.delete') }}</x-ui.button>
                </div>
            @endif
        </div>
    </div>

    @unless ($draft)
        <x-slot:footer>
            <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
        </x-slot:footer>
    @endunless
</x-ui.window>
