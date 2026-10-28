<x-ui.window :title="$record === null ? __('calendar.form.create') : __('calendar.form.edit')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('calendar.form.title')" wire:model="title" maxlength="255" required autofocus /></div>
        <x-ui.date-input :label="__('calendar.form.date')" wire:model="date" />
        <div class="flex items-end"><x-ui.toggle :label="__('calendar.all_day')" wire:model.live="allDay" /></div>

        @unless ($allDay)
            <x-ui.time-input :label="__('calendar.form.start')" wire:model.live="startTime" />
            <x-ui.time-input :label="__('calendar.form.end')" wire:model="endTime" />
            <div class="is-full flex gap-2">
                @foreach ($durations as $minutes => $on)
                    <x-ui.chip wire:key="duration-{{ $minutes }}" wire:click="pickDuration({{ $minutes }})" :on="$on">{{ __('calendar.form.duration.'.$minutes) }}</x-ui.chip>
                @endforeach
            </div>
        @endunless

        <div class="is-full"><x-ui.select :label="__('calendar.form.customer')" wire:model="customerId" :options="$customers" :searchable="true" /></div>
        <div class="is-full"><x-ui.input :label="__('calendar.form.location')" wire:model="location" maxlength="255" /></div>

        <div class="is-full flex flex-col gap-2">
            <span class="ui-label">{{ __('calendar.form.attendees') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($people as $id => $name)
                    <x-ui.chip wire:key="attendee-{{ $id }}" wire:click="toggleAttendee('{{ $id }}')" :on="in_array((string) $id, $attendeeIds, true)">{{ $name }}</x-ui.chip>
                @endforeach
            </div>
        </div>

        <x-ui.select :label="__('calendar.form.reminder')" wire:model="remindMinutes" :options="$reminders" />
        <x-ui.select :label="__('calendar.form.repeat')" wire:model.live="repeat" :options="$repeats" />
        @if ($repeat !== 'none')
            <div class="is-full"><x-ui.date-input :label="__('calendar.form.repeat_until')" wire:model="repeatUntil" /></div>
            @if ($record !== null)
                <p class="ui-hint is-full">{{ __('calendar.form.series_hint') }}</p>
            @endif
        @endif

        <div class="is-full"><x-ui.input type="textarea" :label="__('calendar.form.notes')" wire:model="notes" maxlength="5000" /></div>
    </div>
</x-ui.window>
