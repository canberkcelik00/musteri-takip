@php
    use App\Livewire\Pages\Catalog\Packages;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('packages.name'), 'sortable' => true],
        ['key' => 'catalog_services', 'label' => __('packages.catalog_services'), 'from' => 'md'],
        ['key' => 'price', 'label' => __('packages.price'), 'sortable' => true, 'end' => true],
        ['key' => 'archived_at', 'label' => __('archive.status'), 'from' => 'md'],
    ];
    $filtered = trim($search) !== '' || $archive !== Packages::ARCHIVE_ACTIVE;
@endphp

<div>
    <x-ui.page-header :title="__('packages.plural')">
        <x-ui.button x-on:click="{!! Js::openWindow('edit-package') !!}">{{ __('packages.add') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :count="__('packages.count', ['count' => $packages->total()])" :columns="$columns" :sort="$sort" :direction="$direction" :end="true" :empty="$packages->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.segmented
                wire:model.live="archive"
                :options="[
                    Packages::ARCHIVE_ACTIVE => __('archive.filter_not_archived'),
                    Packages::ARCHIVE_ARCHIVED => __('archive.filter_archived'),
                    Packages::ARCHIVE_ALL => __('archive.filter_all'),
                ]"
            />
        </x-slot:toolbar>

        @foreach ($packages as $package)
            <x-ui.row wire:key="package-{{ $package->id }}">
                <td>
                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-package', ['package' => $package->id]) !!}">{{ $package->name }}</x-ui.link-button>
                </td>
                <td class="ui-from-md">{{ $package->catalogServices->pluck('name')->sort()->implode(', ') }}</td>
                <td class="is-end"><x-ui.money :amount="$package->price" :currency="$package->currency" /></td>
                <td class="ui-from-md">
                    @if ($package->isArchived())
                        <x-ui.badge>{{ __('archive.status_archived') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @unless ($package->isArchived())
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('edit-package', ['package' => $package->id]) !!}">{{ __('actions.edit') }}</x-ui.button>
                        <x-ui.button size="sm" x-on:click="{!! Js::openWindow('create-quote', ['sold' => 'package:'.$package->id]) !!}">{{ __('quotes.create') }}</x-ui.button>
                    @endunless
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('packages.empty_heading')" :description="__('packages.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('edit-package') !!}">{{ __('packages.add') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $packages->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
