@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$record === null ? __('roles.add') : __('roles.edit')" submit="save">
    <div class="ui-form">
        <div class="is-full">
            <x-ui.input :label="__('roles.name')" wire:model="name" maxlength="255" required :disabled="$locked || $builtIn" autofocus />
            @if ($builtIn)
                <span class="ui-hint">{{ __('roles.built_in') }}</span>
            @endif
        </div>

        @if ($locked)
            <p class="ui-hint is-full">{{ __('roles.locked') }}</p>
        @endif

        @foreach ($groups as $group => $items)
            <div class="is-full">
                <h3 class="ui-hint-lead">{{ __('roles.group_'.$group) }}</h3>
                <div class="ui-check-list">
                    @foreach ($items as $permission)
                        <div>
                            <x-ui.checkbox :label="$permission->getLabel()" wire:model="permissions" value="{{ $permission->value }}" :disabled="$locked" />
                            <span class="ui-hint">{{ $permission->getDescription() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if ($record !== null && ! $builtIn)
            <div class="ui-actions-section is-full">
                <h3>{{ __('users.actions_section') }}</h3>
                @if ($canDelete)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-role', 'id' => $record->id]) !!}">{{ __('roles.delete') }}</x-ui.button>
                    </div>
                @else
                    <p class="ui-hint">{{ __('roles.in_use') }}</p>
                @endif
            </div>
        @endif
    </div>
</x-ui.window>
