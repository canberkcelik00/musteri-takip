@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$methodId === null ? __('payment_methods.add') : __('payment_methods.singular')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('payment_methods.name')" wire:model="name" maxlength="255" required autofocus /></div>

        @if ($record !== null)
            <div class="ui-actions-section is-full">
                <h3>{{ __('payment_methods.actions_section') }}</h3>
                <div class="ui-actions-row">
                    @if ($record->isArchived())
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'unarchive-payment-method', 'id' => $record->id]) !!}">{{ __('archive.unarchive') }}</x-ui.button>
                    @else
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'archive-payment-method', 'id' => $record->id]) !!}">{{ __('archive.archive') }}</x-ui.button>
                    @endif
                </div>
                @unless ($inUse)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-payment-method', 'id' => $record->id]) !!}">{{ __('archive.delete') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        @endif
    </div>
</x-ui.window>
