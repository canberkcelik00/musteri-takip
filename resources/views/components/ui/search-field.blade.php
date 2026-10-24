@props(['id' => null])

{{--
    A search input: a magnifier on the left, a round gray clear button on the right while the field has text. The
    browser's own cancel button is hidden in CSS. Clearing fires a real `input` event so wire:model sees it.
--}}
<div class="ui-search-input" x-data="{ filled: false }" x-init="filled = $refs.field.value !== ''">
    <x-heroicon-m-magnifying-glass class="ui-search-icon" aria-hidden="true" />
    <input
        @if ($id) id="{{ $id }}" @endif
        type="search"
        x-ref="field"
        x-on:input="filled = $el.value !== ''"
        {{ $attributes->class(['ui-input', 'ui-input-search']) }}
    >
    <button
        type="button"
        class="ui-search-clear"
        x-show="filled"
        x-cloak
        aria-label="{{ __('ui.search.clear') }}"
        x-on:click="$refs.field.value = ''; $refs.field.dispatchEvent(new Event('input', { bubbles: true })); $refs.field.focus()"
    >
        <x-heroicon-s-x-circle aria-hidden="true" />
    </button>
</div>
