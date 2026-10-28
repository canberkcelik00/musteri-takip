{{-- The month view: the weeks of the month, at most three lines a day, then "+N daha". --}}
<div class="cal-month">
    <div class="cal-month-grid cal-month-head">
        @for ($weekday = 1; $weekday <= 7; $weekday++)
            <div class="cal-hd">{{ __('calendar.weekdays_long.'.$weekday) }}</div>
        @endfor
    </div>
    <div class="cal-month-grid">
        @foreach ($cells as $cell)
            <div wire:key="cell-{{ $cell['date']->toDateString() }}" @class(['cal-cell', 'is-out' => ! $cell['inMonth'], 'is-weekend' => $cell['weekend'], 'is-today' => $cell['today']])>
                <button type="button" class="cal-n" wire:click="openDay('{{ $cell['date']->toDateString() }}')">
                    <span @class(['is-today' => $cell['today']])>{{ $cell['label'] }}</span>
                </button>
                @foreach ($cell['shown'] as $entry)
                    @include('livewire.pages.calendar.entry', ['entry' => $entry, 'mode' => 'item'])
                @endforeach
                @if ($cell['more'] > 0)
                    <button type="button" class="cal-more" wire:click="openDay('{{ $cell['date']->toDateString() }}')">{{ __('calendar.more', ['count' => $cell['more']]) }}</button>
                @endif
            </div>
        @endforeach
    </div>
</div>
