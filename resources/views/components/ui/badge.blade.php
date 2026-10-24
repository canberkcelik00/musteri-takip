@props(['tone' => 'gray'])

{{-- Status badge: a 6 px dot and text. Only red, amber, green and gray exist; any other tone is drawn gray. --}}
@php
    $tone = in_array($tone, ['red', 'amber', 'green'], true) ? $tone : 'gray';
@endphp

<span {{ $attributes->class(['ui-badge', "ui-badge-{$tone}" => $tone !== 'gray']) }}>{{ $slot }}</span>
