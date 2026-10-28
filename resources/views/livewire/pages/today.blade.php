@php
    use App\Support\Money;
    use App\Support\Ui\Js;
    use App\Support\Ui\Links;
    use Illuminate\Support\Carbon;

    $columns = [
        ['key' => 'customer', 'label' => __('today.col_customer')],
        ['key' => 'subject', 'label' => __('today.col_subject'), 'from' => 'md'],
        ['key' => 'date', 'label' => __('today.col_due'), 'from' => 'md'],
        ['key' => 'status', 'label' => __('today.col_status'), 'from' => 'md'],
    ];

    if ($canSeeMoney) {
        $columns[] = ['key' => 'amount', 'label' => __('today.col_amount'), 'end' => true];
    }
@endphp

<div>
    <x-ui.page-header :title="__('navigation.today')" :subtitle="$dateText" />

    {{-- Scheduler warning: only while there is a problem. --}}
    @if ($health['message'] !== null)
        <div class="ui-error-box ui-mt-5" role="alert">{{ $health['message'] }}</div>
    @endif
    @if ($health['failedCount'] > 0)
        <div class="ui-error-box ui-mt-3" role="alert">
            <strong>{{ __('scheduler.failed_services', ['count' => $health['failedCount']]) }}</strong><br>
            @foreach ($health['failed'] as $failed)
                <a href="{{ $failed['url'] }}">{{ $failed['label'] }}</a>@if (! $loop->last), @endif
            @endforeach
            @if ($health['more'] > 0) {{ __('scheduler.failed_services_more', ['count' => $health['more']]) }}@endif.
            {{ __('scheduler.failed_services_log') }}
        </div>
    @endif

    {{-- The four numbers, each a link to its filtered list; money only. --}}
    @if ($canSeeMoney)
    <div class="ui-stats">
        <a class="ui-stat ui-pane" href="{{ $stats['total']['url'] }}">
            <span class="ui-stat-label">{{ __('today.total_debt') }}</span>
            <span class="ui-stat-value">{{ $stats['total']['first']?->format() ?? Money::formatTotals([]) }}</span>
            @if ($stats['total']['others'] !== [])
                <span class="ui-stat-sub">+ {{ Money::formatTotals($stats['total']['others']) }}</span>
            @endif
        </a>
        <a class="ui-stat ui-pane" href="{{ $stats['overdue']['url'] }}">
            <span class="ui-stat-label">{{ __('today.overdue') }}</span>
            <span class="ui-stat-value ui-stat-danger">{{ Money::formatTotals($stats['overdue']['totals']) }}</span>
            <span class="ui-stat-sub">{{ __('today.charge_count', ['count' => $stats['overdue']['count']]) }}</span>
        </a>
        <a class="ui-stat ui-pane" href="{{ $stats['month']['url'] }}">
            <span class="ui-stat-label">{{ __('today.due_this_month') }}</span>
            <span class="ui-stat-value">{{ Money::formatTotals($stats['month']['totals']) }}</span>
            @if ($stats['month']['count'] > 0)
                <span class="ui-stat-sub">{{ __('today.charge_count', ['count' => $stats['month']['count']]) }}</span>
            @endif
        </a>
        <a class="ui-stat ui-pane" href="{{ $stats['renewals']['url'] }}">
            <span class="ui-stat-label">{{ __('today.renewals', ['days' => $stats['renewals']['days']]) }}</span>
            <span class="ui-stat-value">{{ __('today.service_count', ['count' => $stats['renewals']['count']]) }}</span>
            @if ($stats['renewals']['nearest'] !== null)
                <span class="ui-stat-sub">{{ __('today.nearest_renewal', ['date' => $stats['renewals']['nearest']->format('d.m.Y')]) }}</span>
            @endif
        </a>
    </div>
    @endif

    {{-- Yapılacaklar --}}
    <x-ui.card tag="section" flush class="ui-mt-5">
        <div class="ui-card-head">
            <h2>{{ __('today.tasks_heading') }}</h2>
            @unless ($isEmpty)
                <span class="ui-muted">{{ __('today.tasks_count', ['count' => $taskCount]) }}</span>
            @endunless
        </div>

        @if ($isEmpty)
            <x-ui.empty-state :heading="__('today.empty')" />
        @else
            <x-ui.table
                :end="true"
                :card="false"
                :columns="$columns"
            >
                @foreach ($rows as $row)
                    <x-ui.row wire:key="task-{{ $loop->index }}">
                        <td>
                            @if ($row['type'] === 'server_quota')
                                <a class="ui-cell-title" href="{{ Links::servers() }}">{{ __('usage.server_owner', ['name' => $row['server_name']]) }}</a>
                            @elseif ($row['customer'] === null)
                                <span class="ui-cell-title">{{ __('tasks.no_customer') }}</span>
                            @else
                                <div class="ui-cell-identity">
                                    <x-ui.avatar :name="$row['customer']->short_name" :id="$row['customer']->id" />
                                    <div>
                                        <a class="ui-cell-title" href="{{ Links::customer($row['customer']) }}">{{ $row['customer']->short_name }}</a>
                                        @if (filled($row['customer']->legal_title))
                                            <span class="ui-sub-line">{{ $row['customer']->legal_title }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            {{-- Below `md` the subject, date and state columns are hidden; they move under the name. --}}
                            <span class="ui-sub-line ui-below-md">{{ $row['subject'] }} · {{ $row['date'] }}</span>
                            <span class="ui-below-md ui-mt-1">
                                @if ($row['tone'])
                                    <x-ui.badge :tone="$row['tone']">{{ $row['status'] }}</x-ui.badge>
                                @else
                                    {{ $row['status'] }}
                                @endif
                            </span>
                        </td>
                        <td class="ui-from-md">{{ $row['subject'] }}</td>
                        <td class="ui-from-md">{{ $row['date'] }}</td>
                        <td class="ui-from-md">
                            @if ($row['tone'])
                                <x-ui.badge :tone="$row['tone']">{{ $row['status'] }}</x-ui.badge>
                            @else
                                {{ $row['status'] }}
                            @endif
                        </td>
                        @if ($canSeeMoney)
                            <td class="is-end">{{ $row['amount'] }}</td>
                        @endif
                        <x-slot:actions>
                            @if ($row['type'] === 'server_quota')
                                <x-ui.button size="sm" :href="Links::servers()">{{ __('today.go_to_server') }}</x-ui.button>
                            @elseif ($row['type'] === 'charge')
                                @unless ($row['customer']->isArchived())
                                    <x-ui.button size="sm" x-on:click="{!! Js::openWindow('record-payment', ['customer' => $row['charge']->customer_id, 'currency' => $row['charge']->currency->value, 'charge' => $row['charge']->id]) !!}">{{ __('payments.record') }}</x-ui.button>
                                    @if ($row['whatsapp'])
                                        <x-ui.tooltip :label="__('reminders.whatsapp.label')" class="ui-from-md">
                                            <a href="{{ $row['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="ui-btn ui-btn-icon ui-btn-sm ui-whatsapp" aria-label="{{ __('reminders.whatsapp.label') }}">@include('livewire.pages.customers.partials.whatsapp-icon')</a>
                                        </x-ui.tooltip>
                                    @endif
                                @endunless
                            @elseif ($row['type'] === 'ssl')
                                <x-ui.button size="sm" x-on:click="{!! Js::openWindow('check-domain', ['domain' => $row['domain']]) !!}">{{ __('domain_check.check') }}</x-ui.button>
                            @elseif ($row['type'] === 'quota')
                                <x-ui.button size="sm" x-on:click="{!! Js::openWindow('view-usage', ['item' => $row['item_id']]) !!}">{{ __('usage.view') }}</x-ui.button>
                            @elseif ($row['type'] === 'task')
                                <x-ui.button size="sm" wire:click="completeTask({{ $row['task']->id }})">{{ __('tasks.complete') }}</x-ui.button>
                            @else
                                <x-ui.button size="sm" x-on:click="{!! Js::openWindow('confirm',['action' => 'confirm-free-continuation', 'id' => $row['service']->id]) !!}">{{ __('customer_services.confirm_free_continuation') }}</x-ui.button>
                                <x-ui.button size="sm" x-on:click="{!! Js::openWindow('remove-free', ['service' => $row['service']->id]) !!}">{{ __('customer_services.remove_free') }}</x-ui.button>
                            @endif
                        </x-slot:actions>
                    </x-ui.row>
                @endforeach
            </x-ui.table>

            @if ($more !== [] || $quotaMore > 0 || $sslMore > 0)
                <div class="ui-more">
                    @foreach ($more as $section)
                        <a href="{{ $section['url'] }}">{{ $section['label'] }}: {{ __('today.view_all', ['count' => $section['count']]) }}</a>
                    @endforeach
                    @if ($quotaMore > 0)
                        <span>{{ __('today.section_quota') }}: {{ __('today.quota_more', ['count' => $quotaMore]) }}</span>
                    @endif
                    @if ($sslMore > 0)
                        <span>{{ __('today.section_ssl') }}: {{ __('today.quota_more', ['count' => $sslMore]) }}</span>
                    @endif
                </div>
            @endif
        @endif
    </x-ui.card>
</div>
