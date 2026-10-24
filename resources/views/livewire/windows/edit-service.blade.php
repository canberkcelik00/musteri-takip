@php
    use App\Enums\ServiceType;
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="__('customer_services.edit_heading')" submit="save">
    <div class="ui-form">
        @if ($locked)
            <p class="ui-note is-full">{{ __('customer_services.cancelled_locked_helper') }}</p>
        @endif

        <x-ui.money-input :label="__('customer_services.price')" wire:model="price" :suffix="$currencyLabel" :disabled="$locked" />
        <x-ui.select :label="__('money.currency')" wire:model.live="currency" :options="$currencies" />
        <x-ui.date-input :label="__('customer_services.start_date')" wire:model="start_date" />
        <x-ui.date-input :label="__('customer_services.end_date')" wire:model="end_date" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('customer_services.notes')" wire:model="notes" /></div>

        @foreach ($service->items as $item)
            <h3 class="ui-form-section is-full" wire:key="item-{{ $item->id }}-title">{{ __('customer_services.items_section', ['name' => $item->name, 'type' => $item->type->getLabel()]) }}</h3>
            @foreach ($item->type->customerDetailKeys() as $key)
                <x-ui.input wire:key="item-{{ $item->id }}-{{ $key }}" :label="$item->type->customerDetailLabel($key)" wire:model="items.{{ $item->id }}.details.{{ $key }}" maxlength="255" />
            @endforeach
            @if ($item->type === ServiceType::License)
                <x-ui.input wire:key="item-{{ $item->id }}-license" :label="__('customer_services.license_key')" wire:model="items.{{ $item->id }}.license_key" maxlength="255" />
            @endif

            @if (isset($usage[$item->id]))
                @if ($usage[$item->id]['otherDomains'] !== [])
                    <p class="ui-hint is-full">{{ __('usage.other_domains', ['domains' => implode(', ', $usage[$item->id]['otherDomains'])]) }}</p>
                @endif
                <p class="ui-hint is-full">{{ filled($usage[$item->id]['summary']) ? $usage[$item->id]['summary'] : __('usage.summary_none') }}</p>
                <div class="is-full">
                    <x-ui.button x-on:click="{!! Js::openWindow('view-usage', ['item' => $item->id]) !!}">{{ __('usage.view') }}</x-ui.button>
                </div>
            @endif
        @endforeach

        <div class="ui-actions-section is-full">
            <h3>{{ __('customer_services.actions_section') }}</h3>
            <div class="ui-actions-row">
                @if ($locked)
                    <x-ui.button x-on:click="{!! Js::openWindow('reactivate-service', ['service' => $service->id]) !!}">{{ __('customer_services.reactivate') }}</x-ui.button>
                @endif
                @if ($active && ! $service->is_free)
                    <x-ui.button x-on:click="{!! Js::openWindow('mark-free', ['service' => $service->id]) !!}">{{ __('customer_services.mark_free') }}</x-ui.button>
                @endif
                @if ($active && $service->is_free)
                    <x-ui.button x-on:click="{!! Js::openWindow('remove-free', ['service' => $service->id]) !!}">{{ __('customer_services.remove_free') }}</x-ui.button>
                @endif
            </div>
            @if ($active || ! $inUse)
                <div class="ui-actions-row">
                    @if ($active)
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('cancel-service', ['service' => $service->id]) !!}">{{ __('customer_services.cancel') }}</x-ui.button>
                    @endif
                    @unless ($inUse)
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-service', 'id' => $service->id]) !!}">{{ __('customer_services.delete') }}</x-ui.button>
                    @endunless
                </div>
            @endif
        </div>
    </div>
</x-ui.window>
