@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$packageId === null ? __('packages.add') : __('packages.singular')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('packages.name')" wire:model="name" maxlength="255" required autofocus /></div>

        <div class="ui-field is-full">
            <span class="ui-label">{{ __('packages.catalog_services') }}</span>
            <div class="ui-check-list">
                @forelse ($services as $id => $label)
                    <x-ui.checkbox :wire:key="'package-service-'.$id" wire:model="serviceIds" :value="$id" :label="$label" />
                @empty
                    <span class="ui-hint">{{ __('packages.no_services') }}</span>
                @endforelse
            </div>
            <span class="ui-hint">{{ __('packages.catalog_services_helper') }}</span>
            @error('serviceIds') <span class="ui-error">{{ $message }}</span> @enderror
        </div>

        <x-ui.money-input :label="__('packages.price')" wire:model="price" />
        <x-ui.select :label="__('money.currency')" wire:model="currency" :options="$currencies" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('packages.notes')" wire:model="notes" /></div>

        @if ($record !== null)
            <div class="ui-actions-section is-full">
                <h3>{{ __('packages.actions_section') }}</h3>
                <div class="ui-actions-row">
                    @if ($record->isArchived())
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'unarchive-package', 'id' => $record->id]) !!}">{{ __('archive.unarchive') }}</x-ui.button>
                    @else
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'archive-package', 'id' => $record->id]) !!}">{{ __('archive.archive') }}</x-ui.button>
                    @endif
                </div>
                @unless ($inUse)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-package', 'id' => $record->id]) !!}">{{ __('archive.delete') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        @endif
    </div>
</x-ui.window>
