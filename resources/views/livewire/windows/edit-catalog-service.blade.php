@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$serviceId === null ? __('catalog_services.add') : __('catalog_services.singular')" submit="save">
    <div class="ui-form">
        <x-ui.select :label="__('catalog_services.type')" wire:model.live="type" :options="$types" />
        <x-ui.input :label="__('catalog_services.name')" wire:model="name" maxlength="255" required autofocus />
        <x-ui.money-input :label="__('catalog_services.price')" wire:model="price" />
        <x-ui.select :label="__('money.currency')" wire:model="currency" :options="$currencies" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('catalog_services.notes')" wire:model="notes" /></div>

        @if ($detailLabels !== [])
            <h3 class="ui-form-section is-full">{{ __('catalog_services.details_section') }}</h3>
            @foreach ($detailLabels as $key => $detailLabel)
                <x-ui.input wire:key="detail-{{ $type }}-{{ $key }}" :label="$detailLabel" wire:model="details.{{ $key }}" maxlength="255" />
            @endforeach
        @endif

        @if ($record !== null)
            <div class="ui-actions-section is-full">
                <h3>{{ __('catalog_services.actions_section') }}</h3>
                <div class="ui-actions-row">
                    @if ($record->isArchived())
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'unarchive-catalog-service', 'id' => $record->id]) !!}">{{ __('archive.unarchive') }}</x-ui.button>
                    @else
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'archive-catalog-service', 'id' => $record->id]) !!}">{{ __('archive.archive') }}</x-ui.button>
                    @endif
                </div>
                @unless ($inUse)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-catalog-service', 'id' => $record->id]) !!}">{{ __('archive.delete') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        @endif
    </div>
</x-ui.window>
