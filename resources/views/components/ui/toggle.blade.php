@props(['label' => null])

{{-- An iOS switch: green when on. Bind it with wire:model (true/false). --}}
<label class="ui-check">
    <button
        type="button"
        role="switch"
        class="ui-toggle"
        x-data="{ on: false }"
        x-modelable="on"
        x-on:click="on = ! on"
        x-bind:class="{ 'is-on': on }"
        x-bind:aria-checked="on"
        {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
        {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model']) }}
    ></button>
    @if ($label)
        <span>{{ $label }}</span>
    @endif
</label>
