@php
    use App\Support\Ui\Js;
@endphp

<x-ui.window :title="$record === null ? __('tasks.create') : __('tasks.edit')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('tasks.title')" wire:model="title" maxlength="255" required autofocus /></div>
        <div class="is-full"><x-ui.select :label="__('tasks.customer')" wire:model="customerId" :options="$customers" :searchable="true" />
            @if ($matchedBy !== null && $customerId !== '')
                <p class="ui-hint">{{ __('tasks.matched_from', ['sign' => $matchedBy]) }}</p>
            @endif
        </div>
        <x-ui.select :label="__('tasks.assignee')" wire:model="assignedTo" :options="$assignees" />
        <div class="flex flex-col gap-2">
            <x-ui.date-input :label="__('tasks.due_date')" wire:model="dueDate" />
            <div class="flex gap-2">
                <x-ui.chip wire:click="pickDueDate('today')" :on="$dueDate === today()->format('Y-m-d')">{{ __('tasks.pick_today') }}</x-ui.chip>
                <x-ui.chip wire:click="pickDueDate('tomorrow')" :on="$dueDate === today()->addDay()->format('Y-m-d')">{{ __('tasks.pick_tomorrow') }}</x-ui.chip>
                <x-ui.chip wire:click="pickDueDate('next-week')" :on="$dueDate === today()->addDays(7)->format('Y-m-d')">{{ __('tasks.pick_next_week') }}</x-ui.chip>
            </div>
        </div>
        <div class="is-full"><x-ui.input type="textarea" :label="__('tasks.notes')" wire:model="notes" maxlength="5000" /></div>

        @if ($record !== null)
            <div class="ui-actions-section is-full">
                <h3>{{ __('tasks.actions_section') }}</h3>
                <p class="ui-hint">
                    {{ __('tasks.created_by', ['name' => $record->creator?->name ?? __('money.empty'), 'date' => $record->created_at->format('d.m.Y')]) }}
                    @if ($record->isCompleted())
                        <br>{{ __('tasks.completed_by', ['name' => $record->completer?->name ?? __('money.empty'), 'date' => $record->completed_at->format('d.m.Y')]) }}
                    @endif
                </p>
                <div class="ui-actions-row">
                    @if ($record->isCompleted())
                        <x-ui.button wire:click="reopen">{{ __('tasks.reopen') }}</x-ui.button>
                    @else
                        <x-ui.button wire:click="complete">{{ __('tasks.complete') }}</x-ui.button>
                    @endif
                </div>
                <div class="ui-actions-row">
                    <x-ui.button variant="danger" x-on:click="{!! Js::openWindow('confirm', ['action' => 'delete-task', 'id' => $record->id]) !!}">{{ __('tasks.delete') }}</x-ui.button>
                </div>
            </div>
        @endif
    </div>
</x-ui.window>
