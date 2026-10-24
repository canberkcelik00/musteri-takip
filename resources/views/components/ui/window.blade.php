@props(['title'])

{{--
    A centered glass window over a dimmed layer. Rendered inside WindowHost (end of body), so no bar can trap it.
    Esc or a click on the layer closes it; the footer slot holds "Vazgeç" on the left and the one primary button
    on the right (docs/UI.md §5).
--}}
<div
    class="ui-window-layer"
    x-data
    x-on:keydown.escape.window="$dispatch('close-window')"
    x-on:mousedown.self="$dispatch('close-window')"
>
    <div class="ui-window ui-glass ui-glass-menu" role="dialog" aria-modal="true" aria-label="{{ $title }}" x-trap.noscroll>
        <div class="ui-window-header">{{ $title }}</div>
        <div class="ui-window-body">{{ $slot }}</div>
        @isset($footer)
            <div class="ui-window-footer">{{ $footer }}</div>
        @endisset
    </div>
</div>
