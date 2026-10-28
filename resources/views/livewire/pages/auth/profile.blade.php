@php
    use App\Enums\McpAccess;
    use App\Support\Ui\Js;
@endphp

<div>
    <x-ui.page-header :title="__('ui.profile.title')" :subtitle="__('ui.profile.subtitle')" />

    <div class="mt-6 flex max-w-xl flex-col gap-5">
        <x-ui.card tag="form" wire:submit="saveProfile" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('ui.profile.account') }}</h2></div>
            <x-ui.input :label="__('ui.profile.name')" wire:model="name" autocomplete="name" required />
            <x-ui.input type="email" :label="__('ui.profile.email')" wire:model="email" autocomplete="email" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.save') }}</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card tag="form" wire:submit="changePassword" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('ui.profile.password_section') }}</h2></div>
            <x-ui.input type="password" :label="__('ui.profile.current_password')" wire:model="current_password" autocomplete="current-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password')" wire:model="password" autocomplete="new-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password_confirmation')" wire:model="password_confirmation" autocomplete="new-password" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.change_password') }}</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Claude access (U-42) --}}
        <x-ui.card tag="section" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('mcp.heading') }}</h2></div>
            <p class="ui-hint ui-m-0">{{ __('mcp.description') }}</p>

            <form wire:submit="createToken" class="flex flex-col gap-4">
                <x-ui.input :label="__('mcp.name')" wire:model="token_name" maxlength="100" :placeholder="__('mcp.name_placeholder')" required />
                <div class="ui-field">
                    <span class="ui-label">{{ __('mcp.access') }}</span>
                    <x-ui.segmented
                        wire:model="token_access"
                        :aria-label="__('mcp.access')"
                        :options="collect(McpAccess::cases())->mapWithKeys(fn (McpAccess $access) => [$access->value => $access->getLabel()])->all()"
                    />
                    @error('token_access')
                        <span class="ui-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit">{{ __('mcp.create') }}</x-ui.button>
                </div>
            </form>

            @if ($new_token !== null)
                <div class="flex flex-col gap-2" x-data="{ copied: null }">
                    @foreach (['token' => $new_token, 'command' => $command] as $key => $value)
                        <div class="ui-field">
                            <span class="ui-label">{{ __('mcp.new_'.$key) }}</span>
                            <div class="ui-note flex items-start gap-2">
                                <code class="min-w-0 flex-1 font-mono break-all">{{ $value }}</code>
                                <x-ui.tooltip :label="__('mcp.copy')"><button
                                    type="button"
                                    class="ui-icon-link"
                                    aria-label="{{ __('mcp.copy') }}"
                                    x-on:click="navigator.clipboard.writeText(@js($value)).then(() => { copied = @js($key); setTimeout(() => copied = null, 1500) })"
                                >
                                    <x-heroicon-o-clipboard-document x-show="copied !== '{{ $key }}'" aria-hidden="true" />
                                    <x-heroicon-o-check x-show="copied === '{{ $key }}'" x-cloak aria-hidden="true" />
                                </button></x-ui.tooltip>
                            </div>
                        </div>
                    @endforeach
                    <span class="ui-hint">{{ __('mcp.shown_once') }}</span>
                </div>
            @endif

            @if ($tokens->isNotEmpty())
                <x-ui.table
                    :card="false"
                    :end="true"
                    :columns="[
                        ['key' => 'name', 'label' => __('mcp.name')],
                        ['key' => 'access', 'label' => __('mcp.access'), 'from' => 'md'],
                        ['key' => 'last_used', 'label' => __('mcp.last_used'), 'from' => 'md'],
                        ['key' => 'expires', 'label' => __('mcp.expires')],
                    ]"
                >
                    @foreach ($tokens as $token)
                        <x-ui.row wire:key="mcp-token-{{ $token->id }}">
                            <td class="ui-cell-title">{{ $token->name }}</td>
                            <td class="ui-from-md">{{ McpAccess::fromAbilities($token->abilities ?? [])->getLabel() }}</td>
                            <td class="ui-from-md">{{ $token->last_used_at?->format('d.m.Y H:i') ?? __('mcp.never_used') }}</td>
                            <td>
                                @if ($token->expires_at !== null && $token->expires_at->isPast())
                                    <x-ui.badge tone="red">{{ __('mcp.expired') }}</x-ui.badge>
                                @else
                                    {{ $token->expires_at?->format('d.m.Y') }}
                                @endif
                            </td>
                            <x-slot:actions>
                                <x-ui.button variant="danger" size="sm" x-on:click="{!! Js::openWindow('confirm', ['action' => 'revoke-mcp-token', 'id' => $token->id]) !!}">{{ __('mcp.revoke') }}</x-ui.button>
                            </x-slot:actions>
                        </x-ui.row>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</div>
