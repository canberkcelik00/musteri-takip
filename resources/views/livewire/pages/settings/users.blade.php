@php
    use App\Enums\Role;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('users.name')],
        ['key' => 'email', 'label' => __('users.email'), 'from' => 'md'],
        ['key' => 'role', 'label' => __('users.role')],
        ['key' => 'status', 'label' => __('users.status'), 'from' => 'md'],
    ];
@endphp

<div>
    <x-ui.page-header :title="__('users.plural')" :subtitle="__('users.subtitle')">
        <x-ui.button x-on:click="{!! Js::openWindow('invite-user') !!}">{{ __('users.invite') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :columns="$columns" :end="true" :empty="$users->isEmpty()">
        @foreach ($users as $user)
            @php
                $roleName = (string) $user->roles->first()?->name;
                $isSelf = $user->id === $currentId;
            @endphp
            <x-ui.row wire:key="user-{{ $user->id }}">
                <td><x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-user', ['user' => $user->id]) !!}">{{ $user->name }}</x-ui.link-button></td>
                <td class="ui-from-md">{{ $user->email }}</td>
                <td>{{ $roleName === '' ? '—' : Role::labelFor($roleName) }}</td>
                <td class="ui-from-md">
                    @if (! $user->isActive())
                        <x-ui.badge>{{ __('users.status_deactivated') }}</x-ui.badge>
                    @elseif ($user->isInvitationPending())
                        <x-ui.badge tone="amber">{{ __('users.status_invited') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="green">{{ __('users.status_active') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @if ($user->isInvitationPending())
                        <x-ui.button size="sm" wire:click="resend({{ $user->id }})">{{ __('users.resend') }}</x-ui.button>
                    @endif
                    <x-ui.button size="sm" x-on:click="{!! Js::openWindow('edit-user', ['user' => $user->id]) !!}">{{ __('actions.edit') }}</x-ui.button>
                    @unless ($isSelf)
                        @if ($user->isActive())
                            <x-ui.button size="sm" wire:click="deactivate({{ $user->id }})">{{ __('users.deactivate') }}</x-ui.button>
                        @else
                            <x-ui.button size="sm" wire:click="reactivate({{ $user->id }})">{{ __('users.reactivate') }}</x-ui.button>
                        @endif
                    @endunless
                </x-slot:actions>
            </x-ui.row>
        @endforeach
    </x-ui.table>
</div>
