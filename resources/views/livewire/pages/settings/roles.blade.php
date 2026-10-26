@php
    use App\Enums\Role;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('roles.name')],
        ['key' => 'permissions', 'label' => __('roles.permissions')],
        ['key' => 'users', 'label' => __('roles.users')],
    ];
@endphp

<div>
    <x-ui.page-header :title="__('roles.plural')" :subtitle="__('roles.subtitle')">
        <x-ui.button x-on:click="{!! Js::openWindow('edit-role') !!}">{{ __('roles.add') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :columns="$columns" :end="true" :empty="$roles->isEmpty()">
        @foreach ($roles as $role)
            <x-ui.row wire:key="role-{{ $role->id }}">
                <td>
                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-role', ['role' => $role->id]) !!}">{{ Role::labelFor($role->name) }}</x-ui.link-button>
                    @if ($role->name === Role::Admin->value)
                        <x-ui.badge>{{ __('roles.locked_badge') }}</x-ui.badge>
                    @endif
                </td>
                <td>{{ $role->permissions_count }}</td>
                <td>{{ $role->users_count }}</td>
                <x-slot:actions>
                    <x-ui.button size="sm" x-on:click="{!! Js::openWindow('edit-role', ['role' => $role->id]) !!}">{{ $role->name === Role::Admin->value ? __('roles.view') : __('actions.edit') }}</x-ui.button>
                </x-slot:actions>
            </x-ui.row>
        @endforeach
    </x-ui.table>
</div>
