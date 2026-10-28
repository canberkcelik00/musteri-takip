@php
    use App\Support\Ui\Js;

    /*
     * One calendar entry in one of four shapes: `block` (timed, placed on the hour grid), `chip` (all day), `item`
     * (a month cell line) and `row` (the list). A click opens the entry's window, shows an event's detail, or goes to
     * the entry's page.
     */
    $mode = $mode ?? 'chip';
    $withDetail = $withDetail ?? false;
    $placed = $placed ?? null;
    $color = $entry->color();
    $tag = $entry->url !== null ? 'a' : 'button';
    $time = $entry->start === null ? null : $entry->start.($entry->end !== null ? ' – '.$entry->end : '');
    $isSelected = $selected === $entry->key;
    $classes = 'cal-entry cal-'.$mode.' cal-c-'.$color.($entry->recorded ? ' is-recorded' : '').($entry->allDay ? ' is-allday' : '').($isSelected ? ' is-selected' : '');
    $style = null;

    if ($placed !== null) {
        $style = sprintf(
            'top: calc(var(--cal-hour) * %s); height: calc(var(--cal-hour) * %s); left: calc(%s%% + 3px); width: calc(%s%% - 6px);',
            round($placed['from'] / 60, 4),
            round(($placed['to'] - $placed['from']) / 60, 4),
            round(100 / $placed['lanes'] * $placed['lane'], 4),
            round(100 / $placed['lanes'], 4),
        );
    }
@endphp
<{{ $tag }}
    @if ($entry->url !== null)
        href="{{ $entry->url }}" wire:navigate
    @elseif ($entry->isEditable())
        type="button" wire:click="select('{{ $entry->key }}', '{{ $entry->date->toDateString() }}')"
    @else
        type="button" x-data x-on:click="{!! Js::openWindow((string) $entry->window, $entry->params) !!}"
    @endif
    class="{{ $classes }}"
    @if ($style !== null) data-geometry style="{{ $style }}" @endif
>
    @if ($mode === 'block')
        <b>{{ $entry->title }}</b>
        @if ($placed !== null && $placed['to'] - $placed['from'] > 40)
            <span>{{ $time }}@if ($entry->detail) · {{ $entry->detail }}@endif</span>
        @elseif ($entry->start !== null)
            <span>· {{ $entry->start }}</span>
        @endif
    @elseif ($mode === 'chip')
        <span class="cal-dot" aria-hidden="true"></span>
        <span class="cal-text">{{ $entry->title }}@if ($withDetail && $entry->detail) · {{ $entry->detail }}@endif</span>
    @elseif ($mode === 'item')
        <span class="cal-dot" aria-hidden="true"></span>
        @if ($entry->start !== null)
            <time>{{ $entry->start }}</time>
        @endif
        <span class="cal-text">{{ $entry->title }}</span>
    @else
        <time>
            @if ($entry->start === null)
                {{ __('calendar.all_day') }}
            @else
                {{ $entry->start }}@if ($entry->end !== null)<br>{{ $entry->end }}@endif
            @endif
        </time>
        <span class="cal-bar" aria-hidden="true"></span>
        <span class="cal-row-text">
            <span class="cal-row-title">{{ $entry->title }}</span>
            @if ($entry->detail)
                <span class="cal-row-sub">{{ $entry->detail }}</span>
            @endif
        </span>
    @endif
</{{ $tag }}>
