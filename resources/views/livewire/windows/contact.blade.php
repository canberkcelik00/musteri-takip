@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$contactId === null ? __('contacts.add') : __('contacts.singular')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('contacts.name')" wire:model="name" maxlength="255" required autofocus /></div>
        <x-ui.input :label="__('contacts.title')" wire:model="title" maxlength="255" />
        <x-ui.input type="tel" :label="__('contacts.phone')" wire:model="phone" maxlength="255" />
        <div class="is-full"><x-ui.input type="email" :label="__('contacts.email')" wire:model="email" maxlength="255" /></div>

        @if ($contactId === null)
            <div class="is-full"><x-ui.toggle wire:model="make_primary" :label="__('contacts.make_primary_on_create')" /></div>
        @elseif (! $isPrimary)
            <div class="ui-actions-section is-full">
                <h3>{{ __('contacts.actions_section') }}</h3>
                <div class="ui-actions-row">
                    <x-ui.button wire:click="makePrimary">{{ __('contacts.make_primary') }}</x-ui.button>
                </div>
                <div class="ui-actions-row">
                    <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-contact', 'id' => $contactId]) !!}">{{ __('contacts.delete') }}</x-ui.button>
                </div>
            </div>
        @endif
    </div>
</x-ui.window>
