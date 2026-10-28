{{-- The list view (and the week on a narrow screen): a strip of the week's days, then the days that have entries. --}}
<div class="cal-list">
    <div class="ui-pane cal-strip" role="group" aria-label="{{ __('calendar.views.week') }}">
        @foreach ($strip as $day)
            <button type="button" wire:click="pickDay('{{ $day['date']->toDateString() }}')" @class(['cal-strip-day', 'is-picked' => $day['picked']]) aria-pressed="{{ $day['picked'] ? 'true' : 'false' }}">
                <small>{{ $day['min'] }}</small>
                <b @class(['is-today' => $day['today']])>{{ $day['number'] }}</b>
                <i @class(['is-empty' => ! $day['has']]) aria-hidden="true"></i>
            </button>
        @endforeach
    </div>

    @forelse ($groups as $group)
        <section wire:key="group-{{ $group['date']->toDateString() }}">
            <h2 class="cal-group-title">{{ $group['label'] }}</h2>
            <div class="ui-pane cal-rows">
                @foreach ($group['entries'] as $entry)
                    @include('livewire.pages.calendar.entry', ['entry' => $entry, 'mode' => 'row'])
                @endforeach
            </div>
        </section>
    @empty
        <p class="ui-pane cal-empty">{{ __('calendar.nothing') }}</p>
    @endforelse
</div>
