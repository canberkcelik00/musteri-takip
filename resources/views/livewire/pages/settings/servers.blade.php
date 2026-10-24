@php
    use App\Enums\ServerType;
    use App\Livewire\Pages\Settings\Servers;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('servers.name')],
        ['key' => 'type', 'label' => __('servers.type'), 'from' => 'md'],
        ['key' => 'hostname', 'label' => __('servers.hostname'), 'from' => 'lg'],
        ['key' => 'last_pulled_at', 'label' => __('servers.last_pulled_at'), 'from' => 'md'],
        ['key' => 'usage', 'label' => __('usage.servers_column'), 'from' => 'md'],
        ['key' => 'archived_at', 'label' => __('archive.status'), 'from' => 'lg'],
    ];
    $filtered = $archive !== Servers::ARCHIVE_ACTIVE;
@endphp

<div>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="ui-title">{{ __('servers.plural') }}</h1>
            <p class="ui-subtitle">{{ __('servers.count', ['count' => $servers->count()]) }}</p>
        </div>
        <x-ui.button x-on:click="{!! Js::openWindow('edit-server') !!}">{{ __('servers.add') }}</x-ui.button>
    </div>

    <x-ui.table class="mt-5" :columns="$columns" :end="true" :empty="$servers->isEmpty()">
        <x-slot:toolbar>
            <x-ui.segmented
                wire:model.live="archive"
                :options="[
                    Servers::ARCHIVE_ACTIVE => __('archive.filter_not_archived'),
                    Servers::ARCHIVE_ARCHIVED => __('archive.filter_archived'),
                    Servers::ARCHIVE_ALL => __('archive.filter_all'),
                ]"
            />
        </x-slot:toolbar>

        @foreach ($servers as $server)
            @php
                $summary = $usage[$server->id];
            @endphp
            <x-ui.row wire:key="server-{{ $server->id }}">
                <td>
                    <button type="button" class="ui-link-button ui-cell-title" x-on:click="{!! Js::openWindow('edit-server', ['server' => $server->id]) !!}">{{ $server->name }}</button>
                </td>
                <td class="ui-from-md">{{ $server->type->getLabel() }}</td>
                <td class="ui-from-lg">{{ $server->hostname }}</td>
                <td class="ui-from-md">
                    @if ($server->last_pulled_at)
                        {{ $server->last_pulled_at->format('d.m.Y') }}
                    @else
                        <span class="ui-muted">{{ __('servers.never_pulled') }}</span>
                    @endif
                </td>
                <td class="ui-from-md">
                    @if ($server->type === ServerType::Whm)
                        <button type="button" class="ui-link-button @if ($summary['failed']) is-danger @endif" x-on:click="{!! Js::openWindow('view-server-usage', ['server' => $server->id]) !!}">{{ $summary['text'] }}</button>
                    @else
                        <span @class(['is-danger' => $summary['failed']])>{{ $summary['text'] }}</span>
                    @endif
                    @if ($summary['reseller'])
                        <span class="ui-sub-line @if ($summary['resellerLevel']) ui-level-{{ $summary['resellerLevel']->value }} @endif">{{ $summary['reseller'] }}</span>
                    @endif
                </td>
                <td class="ui-from-lg">
                    @if ($server->isArchived())
                        <x-ui.badge>{{ __('archive.status_archived') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @unless ($server->isArchived())
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('pull-from-server', ['server' => $server->id]) !!}">{{ __('servers.pull') }}</x-ui.button>
                    @endunless
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('servers.empty_heading')" :description="__('servers.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('edit-server') !!}">{{ __('servers.add') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>
    </x-ui.table>
</div>
