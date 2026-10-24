@props(['on' => false])

{{-- A filter toggle: a gray pill, blue while the filter is on. --}}
<button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" {{ $attributes->class(['ui-chip', 'is-on' => $on]) }}>{{ $slot }}</button>
