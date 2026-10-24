@php
    use App\Enums\ServerType;
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$serverId === null ? __('servers.add') : __('servers.singular')" submit="save">
    <div class="ui-form">
        <x-ui.select :label="__('servers.type')" wire:model.live="type" :options="$types" />
        <x-ui.input :label="__('servers.name')" wire:model="name" maxlength="255" required autofocus />
        <x-ui.input :label="__('servers.hostname')" wire:model="hostname" maxlength="255" required />
        <x-ui.input type="number" :label="__('servers.port')" wire:model="port" min="1" max="65535" required />
        <x-ui.input :label="__('servers.username')" wire:model="username" maxlength="255" required autocomplete="off" />
        <div class="is-full">
            <x-ui.input type="password" :label="__('servers.api_token')" wire:model="api_token" :hint="__('servers.api_token_helper')" autocomplete="new-password" />
        </div>

        @if ($type === ServerType::Cpanel->value)
            <div class="is-full">
                <x-ui.select :label="__('servers.catalog_service')" wire:model="catalog_service_id" :options="['' => '—'] + $hostingServices" :searchable="true" />
                <span class="ui-hint">{{ __('servers.catalog_service_helper') }}</span>
            </div>
        @endif

        @if ($record !== null)
            <div class="ui-actions-section is-full">
                <h3>{{ __('servers.actions_section') }}</h3>
                <div class="ui-actions-row">
                    <x-ui.button wire:click="testConnection">{{ __('servers.test_connection') }}</x-ui.button>
                </div>
                <div class="ui-actions-row">
                    @if ($record->isArchived())
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'unarchive-server', 'id' => $record->id]) !!}">{{ __('archive.unarchive') }}</x-ui.button>
                    @else
                        <x-ui.button x-on:click="{!! Js::openWindow('confirm', ['action' => 'archive-server', 'id' => $record->id]) !!}">{{ __('archive.archive') }}</x-ui.button>
                    @endif
                </div>
                @unless ($inUse)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-server', 'id' => $record->id]) !!}">{{ __('archive.delete') }}</x-ui.button>
                    </div>
                @endunless
            </div>
        @endif
    </div>
</x-ui.window>
