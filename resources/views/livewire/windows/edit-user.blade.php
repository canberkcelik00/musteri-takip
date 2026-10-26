@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="__('users.edit')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('users.name')" wire:model="name" maxlength="255" required autofocus /></div>
        <div class="is-full">
            <x-ui.input :label="__('users.email')" :value="$record->email" disabled />
            <span class="ui-hint">{{ __('users.email_fixed') }}</span>
        </div>
        <div class="is-full">
            <x-ui.select :label="__('users.role')" wire:model="role" :options="$roles" :disabled="$isSelf" />
            @if ($isSelf)
                <span class="ui-hint">{{ __('users.own_account') }}</span>
            @endif
        </div>

        @if (! $isSelf)
            <div class="ui-actions-section is-full">
                <h3>{{ __('users.actions_section') }}</h3>
                @if ($canDelete)
                    <div class="ui-actions-row">
                        <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-user', 'id' => $record->id]) !!}">{{ __('users.delete') }}</x-ui.button>
                    </div>
                @else
                    <p class="ui-hint">{{ __('users.has_records') }}</p>
                @endif
            </div>
        @endif
    </div>
</x-ui.window>
