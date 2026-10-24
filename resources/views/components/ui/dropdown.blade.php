@props(['align' => 'left'])

{{-- A glass menu sheet opened by the `trigger` slot (which may call toggle() and read open). Esc and an outside click close it. --}}
<div
    class="ui-dropdown"
    x-data="{ open: false, toggle() { this.open = ! this.open; } }"
    x-on:keydown.escape.stop="open = false"
    x-on:click.outside="open = false"
>
    {{ $trigger }}

    <div {{ $attributes->class(['ui-dropdown-panel', 'ui-glass', 'ui-glass-menu', 'is-right' => $align === 'right']) }} x-show="open" x-cloak role="menu">
        {{ $slot }}
    </div>
</div>
