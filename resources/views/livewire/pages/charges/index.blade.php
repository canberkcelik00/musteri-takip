@php
    use App\Enums\ChargeStatus;
    use App\Support\Money;
    use App\Support\Ui\Js;
    use App\Support\Ui\Links;
    use App\Support\Ui\StatusBadges;
    use Illuminate\Support\Carbon;

    $columns = [
        ['key' => 'customer', 'label' => __('charges.customer')],
        ['key' => 'description', 'label' => __('charges.description'), 'from' => 'md'],
        ['key' => 'due_date', 'label' => __('charges.due_date'), 'from' => 'md', 'sortable' => true],
        ['key' => 'status', 'label' => __('charges.status'), 'from' => 'lg'],
        ['key' => 'remaining', 'label' => __('charges.remaining'), 'end' => true],
    ];
    $fmt = fn ($date) => Carbon::parse($date)->format('d.m.Y');
    $filtered = trim($search) !== '' || $status !== 'open' || $onlyOverdue || $dueThisMonth || $dueSoon || $hideArchived || $currency !== '' || $customer !== '';
@endphp

<div>
    <x-ui.page-header :title="__('navigation.open_charges')" />

    <x-ui.table class="mt-5" :count="__('charges.count', ['count' => $charges->total()])" :columns="$columns" :sort="$sort" :direction="$direction" :end="true" :empty="$charges->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.chip :on="$onlyOverdue" wire:click="toggle('onlyOverdue')">{{ __('charges.only_overdue') }}</x-ui.chip>
            <x-ui.chip :on="$dueThisMonth" wire:click="toggle('dueThisMonth')">{{ __('charges.due_this_month') }}</x-ui.chip>
            <x-ui.chip :on="$dueSoon" wire:click="toggle('dueSoon')">{{ __('charges.due_soon_filter') }}</x-ui.chip>
            <x-ui.chip :on="$hideArchived" wire:click="toggle('hideArchived')">{{ __('charges.hide_archived') }}</x-ui.chip>
            @if ($status !== 'all')
                <x-ui.filter-chip :label="__('charges.status_chip', ['value' => $statuses[$status] ?? $status])" wire:click="$set('status', 'all')" />
            @else
                <x-ui.select width="sm" wire:model.live="status" :options="$statuses" :aria-label="__('charges.status')" />
            @endif
            <x-ui.select width="sm" wire:model.live="currency" :options="$currencies" :aria-label="__('charges.currency')" />
            <x-ui.select width="md" wire:model.live="customer" :options="['' => __('archive.filter_all')] + $customers" :searchable="true" :aria-label="__('charges.customer')" />
        </x-slot:toolbar>

        @foreach ($charges as $charge)
            @php
                $tone = StatusBadges::chargeTone($charge);
                $isOpen = $charge->status === ChargeStatus::Open;
            @endphp
            <x-ui.row wire:key="charge-{{ $charge->id }}">
                <td>
                    <div class="ui-cell-identity">
                        <x-ui.avatar :name="$charge->customer->short_name" :id="$charge->customer_id" />
                        <div>
                            <a class="ui-cell-title" href="{{ Links::customer($charge->customer) }}">{{ $charge->customer->short_name }}</a>
                            <span class="ui-sub-line ui-below-md">{{ $charge->description }}</span>
                            <span class="ui-sub-line ui-below-md">{{ __('charges.due_date') }}: {{ $fmt($charge->due_date) }}</span>
                        </div>
                    </div>
                </td>
                <td class="ui-from-md">{{ $charge->description }}</td>
                <td class="ui-from-md">{{ $fmt($charge->due_date) }}</td>
                <td class="ui-from-lg">
                    @if ($tone)
                        <x-ui.badge :tone="$tone">{{ StatusBadges::chargeLabel($charge) }}</x-ui.badge>
                    @else
                        {{ StatusBadges::chargeLabel($charge) }}
                    @endif
                </td>
                <td class="is-end">
                    @if ($isOpen)
                        <x-ui.money :amount="$charge->remainingAmount()" :currency="$charge->currency" />
                    @else
                        <span class="ui-muted">{{ __('money.empty') }}</span>
                    @endif
                    @if ($charge->isPartiallyPaid())
                        <span class="ui-sub-line">{{ __('charges.original_amount.'.$charge->currency->value, ['amount' => (new Money($charge->amount, $charge->currency))->format()]) }}</span>
                    @endif
                    @if ($tone)
                        <span class="ui-below-lg"><x-ui.badge :tone="$tone">{{ StatusBadges::chargeLabel($charge) }}</x-ui.badge></span>
                    @endif
                </td>
                <x-slot:actions>
                    @if ($isOpen)
                        <x-ui.button size="sm" x-on:click="{!! Js::openWindow('record-payment', ['customer' => $charge->customer_id, 'currency' => $charge->currency->value, 'charge' => $charge->id]) !!}">{{ __('payments.record') }}</x-ui.button>
                        <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('edit-charge', ['charge' => $charge->id]) !!}">{{ __('charges.edit') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        @if ($totals !== [])
            <x-slot:foot>
                @foreach (array_values($totals) as $index => $money)
                    <tr>
                        <td>@if ($index === 0){{ __('charges.total') }}@endif</td>
                        <td class="ui-from-md"></td>
                        <td class="ui-from-md"></td>
                        <td class="ui-from-lg"></td>
                        <td class="is-end"><x-ui.money :amount="$money->amount" :currency="$money->currency" /></td>
                        <td></td>
                    </tr>
                @endforeach
            </x-slot:foot>
        @endif

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('charges.empty_heading')" :description="__('charges.empty_description')" />
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $charges->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
