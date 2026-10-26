@props(['label'])

{{-- A checkbox with its label; bind it with wire:model. --}}
<label {{ $attributes->only('class')->class(['ui-check']) }}>
    <input type="checkbox" {{ $attributes->except('class') }}>
    <span>{{ $label }}</span>
</label>
