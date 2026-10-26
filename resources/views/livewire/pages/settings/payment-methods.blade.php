@php
    use App\Livewire\Pages\Settings\PaymentMethods;
    use App\Support\Ui\Js;

    $columns = [
        ['key' => 'name', 'label' => __('payment_methods.name')],
        ['key' => 'archived_at', 'label' => __('archive.status'), 'from' => 'md'],
    ];
    $filtered = $archive !== PaymentMethods::ARCHIVE_ACTIVE;
    $reorderable = $methods->count() > 1;
@endphp

<div
    x-data="{
        from: null,
        drop(to) {
            if (this.from === null || this.from === to) { this.from = null; return; }
            const ids = [...$root.querySelectorAll('tr[data-id]')].map((row) => Number(row.dataset.id));
            ids.splice(ids.indexOf(this.from), 1);
            ids.splice(ids.indexOf(to), 0, this.from);
            this.from = null;
            $wire.reorder(ids);
        },
    }"
>
    <x-ui.page-header :title="__('payment_methods.plural')" :subtitle="__('payment_methods.order_hint')">
        <x-ui.button x-on:click="{!! Js::openWindow('edit-payment-method') !!}">{{ __('payment_methods.add') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :columns="$columns" :end="true" :empty="$methods->isEmpty()">
        <x-slot:toolbar>
            <x-ui.segmented
                wire:model.live="archive"
                :options="[
                    PaymentMethods::ARCHIVE_ACTIVE => __('archive.filter_not_archived'),
                    PaymentMethods::ARCHIVE_ARCHIVED => __('archive.filter_archived'),
                    PaymentMethods::ARCHIVE_ALL => __('archive.filter_all'),
                ]"
            />
        </x-slot:toolbar>

        @foreach ($methods as $method)
            <x-ui.row
                wire:key="method-{{ $method->id }}"
                data-id="{{ $method->id }}"
                :draggable="$reorderable ? 'true' : 'false'"
                x-on:dragstart="from = {{ $method->id }}"
                x-on:dragover.prevent
                x-on:drop.prevent="drop({{ $method->id }})"
            >
                <td>
                    <x-ui.link-button class="ui-cell-title" x-on:click="{!! Js::openWindow('edit-payment-method', ['method' => $method->id]) !!}">{{ $method->name }}</x-ui.link-button>
                </td>
                <td class="ui-from-md">
                    @if ($method->isArchived())
                        <x-ui.badge>{{ __('archive.status_archived') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @if ($reorderable)
                        <x-ui.button size="sm" wire:click="move({{ $method->id }}, -1)" :disabled="$loop->first">{{ __('actions.up') }}</x-ui.button>
                        <x-ui.button size="sm" wire:click="move({{ $method->id }}, 1)" :disabled="$loop->last">{{ __('actions.down') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('payment_methods.empty_heading')" :description="__('payment_methods.empty_description')">
                    <x-ui.button x-on:click="{!! Js::openWindow('edit-payment-method') !!}">{{ __('payment_methods.add') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>
    </x-ui.table>
</div>
