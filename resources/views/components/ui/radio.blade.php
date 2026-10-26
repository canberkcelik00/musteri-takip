@props(['options' => []])

{{-- A radio group: `options` is ['value' => 'Label']; bind it with wire:model. --}}
<div {{ $attributes->only('class')->class(['ui-radio']) }}>
    @foreach ($options as $value => $label)
        <label><input type="radio" value="{{ $value }}" {{ $attributes->except('class') }}> {{ $label }}</label>
    @endforeach
</div>
