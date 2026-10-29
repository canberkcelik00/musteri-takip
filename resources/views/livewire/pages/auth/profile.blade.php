@php
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

        {{-- Claude access (U-43) --}}
        <x-ui.card tag="section" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('mcp.heading') }}</h2></div>
            <p class="ui-hint ui-m-0">{{ __('mcp.description') }}</p>

            <div class="flex flex-col gap-2" x-data="{ copied: null }">
                @foreach (['url' => $url, 'command' => $command] as $key => $value)
                    <div class="ui-field">
                        <span class="ui-label">{{ __('mcp.'.$key) }}</span>
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
                <span class="ui-hint">{{ __('mcp.how_to') }}</span>
            </div>

            @if ($connections->isNotEmpty())
                <x-ui.table
                    :card="false"
                    :end="true"
                    :columns="[
                        ['key' => 'name', 'label' => __('mcp.connection')],
                        ['key' => 'write', 'label' => __('mcp.write_access')],
                        ['key' => 'last', 'label' => __('mcp.last_issued'), 'from' => 'md'],
                    ]"
                >
                    @foreach ($connections as $row)
                        <x-ui.row wire:key="mcp-connection-{{ $row['connection']->id }}">
                            <td class="ui-cell-title">{{ $row['connection']->client?->name }}</td>
                            <td><x-ui.toggle wire:model.live="write.{{ $row['connection']->id }}" :label="__('mcp.write_label')" /></td>
                            <td class="ui-from-md">{{ $row['last_token_at'] === null ? '' : \Illuminate\Support\Carbon::parse($row['last_token_at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                            <x-slot:actions>
                                <x-ui.button variant="danger" size="sm" x-on:click="{!! Js::openWindow('confirm', ['action' => 'disconnect-mcp', 'id' => $row['connection']->id]) !!}">{{ __('mcp.disconnect') }}</x-ui.button>
                            </x-slot:actions>
                        </x-ui.row>
                    @endforeach
                </x-ui.table>
            @else
                <span class="ui-hint">{{ __('mcp.none') }}</span>
            @endif
        </x-ui.card>
    </div>
</div>
