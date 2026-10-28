{{--
    The hour grid of the week and the day view: a header with the days (week only), the "Tüm gün" row, and 24 hours
    that scroll, opened on 08:00. The "now" line is drawn in today's column.
--}}
@php
    $isWeek = $viewName === 'week';
@endphp
<div @class(['cal-hours', 'is-week' => $isWeek, 'is-day' => ! $isWeek, 'cal-cols-7' => $isWeek, 'cal-cols-1' => ! $isWeek])>
    @if ($isWeek)
        <div class="cal-grid cal-head">
            <div></div>
            @foreach ($columns as $column)
                <button type="button" wire:click="openDay('{{ $column['date']->toDateString() }}')" @class(['cal-dh', 'is-weekend' => $column['weekend']])>
                    <small>{{ $column['short'] }}</small>
                    <strong @class(['is-today' => $column['today']])>{{ $column['number'] }}</strong>
                </button>
            @endforeach
        </div>
    @endif

    <div class="cal-grid cal-allday">
        <div class="cal-allday-label">{{ __('calendar.all_day') }}</div>
        @foreach ($columns as $column)
            <div @class(['cal-col', 'is-weekend' => $column['weekend']])>
                @foreach ($column['allDay'] as $entry)
                    @include('livewire.pages.calendar.entry', ['entry' => $entry, 'mode' => 'chip', 'withDetail' => ! $isWeek])
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="cal-scroll" x-data x-init="$el.scrollTop = parseFloat(getComputedStyle($el).getPropertyValue('--cal-hour')) * 7.5">
        <div class="cal-grid cal-body">
            <div class="cal-labels" aria-hidden="true">
                @for ($hour = 0; $hour < 24; $hour++)
                    <div class="cal-label">@if ($hour > 0){{ sprintf('%02d:00', $hour) }}@endif</div>
                @endfor
            </div>
            @foreach ($columns as $column)
                {{-- An empty slot opens the event window on that day and hour (the hour is read from the click's height). --}}
                <div @class(['cal-col', 'cal-lines', 'is-weekend' => $column['weekend'], 'is-today' => $column['today']])
                    @if ($canManage)
                        x-data
                        x-on:click.self="$dispatch('open-window', { name: 'edit-calendar-event', params: { date: '{{ $column['date']->toDateString() }}', time: String(Math.min(23, Math.floor($event.offsetY / parseFloat(getComputedStyle($el).getPropertyValue('--cal-hour'))))).padStart(2, '0') + ':00' } })"
                    @endif
                >
                    @foreach ($column['placed'] as $placed)
                        @include('livewire.pages.calendar.entry', ['entry' => $placed['entry'], 'mode' => 'block', 'placed' => $placed])
                    @endforeach
                    @if ($column['now'] !== null)
                        <div class="cal-now" data-geometry style="top: calc(var(--cal-hour) * {{ round($column['now'] / 60, 4) }})" title="{{ __('calendar.now') }}" aria-hidden="true">
                            @if (! $isWeek)
                                <span>{{ now()->format('H:i') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
