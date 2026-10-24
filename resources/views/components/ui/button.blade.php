@props(['variant' => 'secondary', 'size' => 'md', 'href' => null, 'type' => 'button', 'icon' => false])

{{--
    A pill button. `primary` is the screen's one filled button, `danger` is tinted red (never a filled red),
    everything else is `secondary`. With `href` it is a link.
--}}
@php
    $classes = [
        'ui-btn',
        'ui-btn-primary' => $variant === 'primary',
        'ui-btn-danger' => $variant === 'danger',
        'ui-btn-sm' => $size === 'sm',
        'ui-btn-icon' => $icon,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
