@props(['tag' => 'div', 'flush' => false])

{{-- A glass card; `tag` lets a form, section or details element be the card itself. `flush` for children with their own padding. --}}
<{{ $tag }} {{ $attributes->class(['ui-card', 'ui-pane', 'is-flush' => $flush]) }}>
    {{ $slot }}
</{{ $tag }}>
