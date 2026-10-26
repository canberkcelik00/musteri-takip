@php
    use App\Livewire\Pages\Customers\Index;
    use App\Support\Avatar;
    use App\Support\Ui\Links;
    use Illuminate\Support\Carbon;

    $columns = [
        ['key' => 'short_name', 'label' => __('customers.short_name'), 'sortable' => true],
        ['key' => 'next_renewal_on', 'label' => __('customers.next_renewal'), 'sortable' => true, 'from' => 'md'],
        ['key' => 'active_services_count', 'label' => __('customers.active_services_count'), 'sortable' => true, 'from' => 'lg', 'end' => true],
        ['key' => 'archived_at', 'label' => __('archive.status'), 'from' => 'md'],
    ];
    if (auth()->user()?->can('money.view')) {
        array_splice($columns, 1, 0, [['key' => 'open_debt', 'label' => __('customers.open_debt'), 'end' => true]]);
    }

    $filtered = trim($search) !== '' || $archive !== Index::ARCHIVE_ACTIVE || $renewalSoon || $awaitingFreeDecision;
@endphp

<div>
    <x-ui.page-header :title="__('customers.plural')">
        <x-ui.button x-on:click="$dispatch('open-window', { name: 'create-customer' })">{{ __('customers.create') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table :count="__('customers.count', ['count' => $total])"
        class="mt-5"
        :columns="$columns"
        :sort="$sort"
        :direction="$direction"
        :end="true"
        :empty="$customers->isEmpty()"
    >
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.segmented
                wire:model.live="archive"
                :options="[
                    Index::ARCHIVE_ACTIVE => __('archive.filter_not_archived'),
                    Index::ARCHIVE_ARCHIVED => __('archive.filter_archived'),
                    Index::ARCHIVE_ALL => __('archive.filter_all'),
                ]"
            />
            <x-ui.chip :on="$renewalSoon" wire:click="toggleRenewalSoon">{{ __('customers.renewal_soon') }}</x-ui.chip>
            <x-ui.chip :on="$awaitingFreeDecision" wire:click="toggleAwaitingFreeDecision">{{ __('customers.awaiting_free_decision') }}</x-ui.chip>
        </x-slot:toolbar>

        @foreach ($customers as $customer)
            <x-ui.row :href="Links::customer($customer)" wire:key="customer-{{ $customer->id }}">
                <td>
                    <div class="ui-cell-identity">
                        <x-ui.avatar :name="$customer->short_name" :id="$customer->id" />
                        <div>
                            <div class="ui-cell-title">{{ $customer->short_name }}</div>
                            @if ($customer->legal_title)
                                <div class="ui-cell-sub">{{ $customer->legal_title }}</div>
                            @endif
                        </div>
                    </div>
                </td>
                @can('money.view')
                    <td class="is-end">
                        @forelse ($customer->openChargeTotals() as $money)
                        <div><x-ui.money :amount="$money->amount" :currency="$money->currency" :danger="(bool) $customer->has_overdue" /></div>
                    @empty
                        <span class="ui-muted">{{ __('money.empty') }}</span>
                    @endforelse
                    </td>
                @endcan
                <td class="ui-from-md">
                    @if ($customer->next_renewal_on)
                        {{ Carbon::parse($customer->next_renewal_on)->format('d.m.Y') }}
                    @else
                        <span class="ui-muted">{{ __('money.empty') }}</span>
                    @endif
                </td>
                <td class="ui-from-lg is-end">{{ $customer->active_services_count }}</td>
                <td class="ui-from-md">
                    @if ($customer->isArchived())
                        <x-ui.badge>{{ __('archive.status_archived') }}</x-ui.badge>
                    @endif
                </td>
                <x-slot:actions>
                    @if (auth()->user()?->can('money.manage') && ! $customer->isArchived())
                        <x-ui.button
                            size="sm"
                            class="ui-from-md"
                            x-on:click="$dispatch('open-window', { name: 'record-payment', params: { customer: {{ $customer->id }} } })"
                        >{{ __('payments.record') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('customers.empty_heading')" :description="__('customers.empty_description')">
                    <x-ui.button x-on:click="$dispatch('open-window', { name: 'create-customer' })">{{ __('customers.create') }}</x-ui.button>
                </x-ui.empty-state>
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $customers->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
