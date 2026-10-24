@props(['options' => []])

{{-- iOS segmented control: a gray capsule, the chosen segment light and shadowed. Bind it with wire:model or x-model. --}}
<div
    role="radiogroup"
    class="ui-segmented"
    x-data="{ value: null }"
    x-modelable="value"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model']) }}
>
    @foreach ($options as $key => $label)
        <button
            type="button"
            role="radio"
            class="ui-segment"
            x-bind:class="{ 'is-active': value === @js((string) $key) }"
            x-bind:aria-checked="value === @js((string) $key)"
            x-on:click="value = @js((string) $key)"
        >{{ $label }}</button>
    @endforeach
</div>
