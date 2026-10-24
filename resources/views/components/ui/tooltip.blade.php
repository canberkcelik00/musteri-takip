@props(['label'])

{{-- Wraps an icon button: the dark pill shows on hover and focus; the button itself carries the accessible name. --}}
<span {{ $attributes->class(['ui-tooltip', 'inline-flex']) }} data-tip="{{ $label }}">{{ $slot }}</span>
