@php
    use App\Livewire\Pages\Catalog\Services;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('catalog_services.name'), 'sortable' => true],
        ['key' => 'type', 'label' => __('catalog_services.type'), 'sortable' => true, 'from' => 'md'],
        ['key' => 'price', 'label' => __('catalog_services.price'), 'sortable' => true, 'end' => true],
        ['key' => 'archived_at', 'label' => __('archive.status'), 'from' => 'md'],
    ];
    $filtered = trim($search) !== '' || $archive !== Services::ARCHIVE_ACTIVE || $type !== Services::TYPE_ALL;
@endphp

<div>
    <x-ui.page-header :title="__('catalog_services.plural')">
        <x-ui.button x-on:click="{!! Js::openWindow('edit-catalog-service') !!}">{{ __('catalog_services.add') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :count="__('catalog_services.count', ['count' => $services->total()])" :columns="$columns" :sort="$sort" :direction="$direction" :end="true" :empty="$services->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.select width="md" wire:model.live="type" :options="$types" :aria-label="__('catalog_services.type')" />
            <x-ui.segmented
                wire:model.live="archive"
                :options="[
                    Services::ARCHIVE_ACTIVE => __('archive.filter_not_archived'),
                    Services::ARCHIVE_ARCHIVED => __('archive.filter_archived'),
                    Services::ARCHIVE_ALL => __('archive.filter_all'),
                ]"
            />
        </x-slot:toolbar>

        @foreach ($services as $service)
            <x-ui.row wire:key="service-{{ $service->id }}">
                <td>
                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-catalog-service', ['service' => $service->id]) !!}">{{ $service->name }}</x-ui.link-button>
                </td>
                <td class="ui-from-md">{{ $service->type->getLabel() }}</td>
                <td class="is-end"><x-ui.money :amount="$service->price" :currency="$service->currency" /></td>
                <td class="ui-from-md">
                    @if ($service->isArchived())
                        <x-ui.badge>{{ __('archive.status_archived') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @unless ($service->isArchived())
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('edit-catalog-service', ['service' => $service->id]) !!}">{{ __('actions.edit') }}</x-ui.button>
                        <x-ui.button size="sm" x-on:click="{!! Js::openWindow('create-quote', ['sold' => 'catalog_service:'.$service->id]) !!}">{{ __('quotes.create') }}</x-ui.button>
                    @endunless
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('catalog_services.empty_heading')" :description="__('catalog_services.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('edit-catalog-service') !!}">{{ __('catalog_services.add') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $services->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
