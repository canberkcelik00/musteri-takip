@php
    use App\Enums\ServiceType;
@endphp

<x-ui.window :title="__('customer_services.assign_heading')" submit="save">
    <div class="ui-form">
        <div class="is-full">
            <x-ui.select :label="__('customer_services.sold')" wire:model.live="sold" :options="$options" :searchable="true" />
        </div>
        <x-ui.money-input :label="__('customer_services.price')" wire:model="price" :suffix="$currencyLabel" />
        <x-ui.select :label="__('money.currency')" wire:model.live="currency" :options="$currencies" />
        <x-ui.date-input :label="__('customer_services.start_date')" wire:model="start_date" />
        <div class="ui-field is-bottom">
            <x-ui.toggle wire:model.live="is_free" :label="__('customer_services.is_free')" />
        </div>

        @unless ($is_free)
            <div class="ui-field is-full">
                <span class="ui-label">{{ __('customer_services.create_first_charge') }}</span>
                <x-ui.radio wire:model="create_first_charge" :options="[1 => __('customer_services.create_first_charge_yes'), 0 => __('customer_services.create_first_charge_no')]" />
                <span class="ui-hint">{{ __('customer_services.create_first_charge_helper') }}</span>
            </div>
        @endunless

        <div class="is-full"><x-ui.input type="textarea" :label="__('customer_services.notes')" wire:model="notes" /></div>

        @foreach ($catalogServices as $catalogService)
            <h3 class="ui-form-section is-full" wire:key="items-{{ $catalogService->id }}-title">{{ __('customer_services.items_section', ['name' => $catalogService->name, 'type' => $catalogService->type->getLabel()]) }}</h3>
            @foreach ($catalogService->type->customerDetailKeys() as $key)
                <x-ui.input wire:key="items-{{ $catalogService->id }}-{{ $key }}" :label="$catalogService->type->customerDetailLabel($key)" wire:model="items.{{ $catalogService->id }}.details.{{ $key }}" maxlength="255" />
            @endforeach
            @if ($catalogService->type === ServiceType::License)
                <x-ui.input wire:key="items-{{ $catalogService->id }}-license" :label="__('customer_services.license_key')" wire:model="items.{{ $catalogService->id }}.license_key" maxlength="255" />
            @endif
        @endforeach
    </div>
</x-ui.window>
