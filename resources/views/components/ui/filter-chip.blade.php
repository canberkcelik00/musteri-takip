@props(['label'])

{{-- A filter that is on and can be removed: the label and a "×" button (its own wire:click / x-on:click). --}}
<span class="ui-chip is-static">
    {{ $label }}
    <button type="button" {{ $attributes->class(['ui-chip-x']) }} aria-label="{{ __('ui.table.remove_filter') }}: {{ $label }}"><x-heroicon-m-x-mark aria-hidden="true" /></button>
</span>
