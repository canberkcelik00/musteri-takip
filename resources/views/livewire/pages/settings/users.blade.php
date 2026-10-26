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
                $role = Role::tryFrom((string) $user->roles->first()?->name);
                $isSelf = $user->id === $currentId;
            @endphp
            <x-ui.row wire:key="user-{{ $user->id }}">
                <td><span class="ui-cell-title">{{ $user->name }}</span></td>
                <td class="ui-from-md">{{ $user->email }}</td>
                <td>{{ $role?->getLabel() }}</td>
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
                    @unless ($isSelf)
                        <x-ui.button size="sm" wire:click="changeRole({{ $user->id }})">
                            {{ $role === Role::Admin ? __('users.make_developer') : __('users.make_admin') }}
                        </x-ui.button>
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
