@props(['href' => null, 'danger' => false, 'block' => false])

{{-- A text-looking button (or a link with `href`) that opens a window or a page: a name in a list, an edit link. --}}
@php
    $classes = ['ui-link-button', 'is-danger' => $danger, 'is-block' => $block];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
