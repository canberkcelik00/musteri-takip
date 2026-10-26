@php
    use App\Livewire\Pages\Tasks\Index;
    use App\Support\Ui\Js;
    use App\Support\Ui\Links;

    $showAssignee = $who === Index::WHO_ALL;
    $columns = [
        ['key' => 'title', 'label' => __('tasks.singular')],
        ['key' => 'customer', 'label' => __('tasks.customer'), 'from' => 'md'],
    ];
    if ($showAssignee) {
        $columns[] = ['key' => 'assignee', 'label' => __('tasks.assignee'), 'from' => 'md'];
    }
    $columns[] = ['key' => 'due_date', 'label' => __('tasks.due_date'), 'end' => true];

    $filtered = trim($search) !== '' || $who !== Index::WHO_MINE || $status !== Index::STATUS_OPEN || $onlyOverdue || $customer !== '';
@endphp

<div>
    <x-ui.page-header :title="__('tasks.plural')">
        <x-ui.button x-on:click="{!! Js::openWindow('edit-task') !!}">{{ __('tasks.create') }}</x-ui.button>
    </x-ui.page-header>

    <x-ui.table class="mt-5" :count="__('tasks.count', ['count' => $tasks->total()])" :columns="$columns" :end="true" :empty="$tasks->isEmpty()">
        <x-slot:toolbar>
            <div class="ui-toolbar-search">
                <x-ui.input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('ui.table.search')" :aria-label="__('ui.table.search')" />
            </div>
            <x-ui.segmented wire:model.live="who" :options="[Index::WHO_MINE => __('tasks.who_mine'), Index::WHO_ALL => __('tasks.who_all')]" />
            @if ($status !== Index::STATUS_ALL)
                <x-ui.filter-chip :label="__('tasks.status_chip', ['value' => $statuses[$status] ?? $status])" wire:click="$set('status', 'all')" />
            @else
                <x-ui.select width="sm" wire:model.live="status" :options="$statuses" :aria-label="__('tasks.status')" />
            @endif
            <x-ui.chip :on="$onlyOverdue" wire:click="toggleOverdue">{{ __('tasks.only_overdue') }}</x-ui.chip>
            <x-ui.select width="md" wire:model.live="customer" :options="['' => __('archive.filter_all')] + $customers" :searchable="true" :aria-label="__('tasks.customer')" />
        </x-slot:toolbar>

        @foreach ($tasks as $task)
            @php
                $customerRecord = $task->customer;
                $overdue = $task->isOverdue();
                $dueToday = $task->isDueToday();
                $dueBadge = match (true) {
                    $overdue => ['red', __('tasks.overdue_days', ['count' => (int) $task->due_date->diffInDays(today(), true)])],
                    $dueToday => ['amber', __('tasks.due_today')],
                    default => null,
                };
            @endphp
            <x-ui.row wire:key="task-{{ $task->id }}" class="cursor-pointer" x-data x-on:click="if (! $event.target.closest('a, button, input, select, textarea, label')) { {!! Js::openWindow('edit-task', ['task' => $task->id]) !!} }">
                <td>
                    <span class="ui-cell-title">{{ $task->title }}</span>
                    @if ($task->notes)
                        <span class="ui-sub-line">{{ \Illuminate\Support\Str::limit(strtok($task->notes, "\n") ?: '', 80) }}</span>
                    @endif
                    @if ($customerRecord)
                        <span class="ui-sub-line ui-below-md @if ($customerRecord->isArchived()) ui-muted @endif">{{ $customerRecord->short_name }}</span>
                    @endif
                </td>
                <td class="ui-from-md">
                    @if ($customerRecord)
                        <a @class(['ui-muted' => $customerRecord->isArchived()]) href="{{ Links::customer($customerRecord) }}">{{ $customerRecord->short_name }}</a>
                        @if ($customerRecord->isArchived())
                            <x-ui.badge tone="gray">{{ __('tasks.archived') }}</x-ui.badge>
                        @endif
                    @else
                        <span class="ui-muted">{{ __('money.empty') }}</span>
                    @endif
                </td>
                @if ($showAssignee)
                    <td class="ui-from-md">
                        {{ $task->assignee->name }}
                        @unless ($task->assignee->isActive())
                            <span class="ui-muted">{{ __('tasks.inactive_suffix') }}</span>
                        @endunless
                    </td>
                @endif
                <td class="is-end">
                    @if ($task->isCompleted())
                        <span class="ui-muted">{{ $task->completed_at->format('d.m.Y') }}</span>
                    @elseif ($task->due_date)
                        {{ $task->due_date->format('d.m.Y') }}
                        @if ($dueBadge)
                            <x-ui.badge :tone="$dueBadge[0]">{{ $dueBadge[1] }}</x-ui.badge>
                        @endif
                    @else
                        <span class="ui-muted">{{ __('money.empty') }}</span>
                    @endif
                </td>
                <x-slot:actions>
                    @if ($task->isCompleted())
                        <x-ui.button size="sm" wire:click="reopen({{ $task->id }})">{{ __('tasks.reopen') }}</x-ui.button>
                    @else
                        <x-ui.button size="sm" wire:click="complete({{ $task->id }})">{{ __('tasks.complete') }}</x-ui.button>
                    @endif
                </x-slot:actions>
            </x-ui.row>
        @endforeach

        <x-slot:emptyState>
            @if ($filtered)
                <x-ui.empty-state :heading="__('ui.table.no_results')" icon="document-magnifying-glass" :description="__('ui.table.no_results_hint')" />
            @else
                <x-ui.empty-state :heading="__('tasks.empty_heading')" :description="__('tasks.empty_description')" icon="clipboard-document-check" />
            @endif
        </x-slot:emptyState>

        <x-slot:pager>
            {{ $tasks->links('ui.pagination') }}
        </x-slot:pager>
    </x-ui.table>
</div>
