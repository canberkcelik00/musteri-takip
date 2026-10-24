@props(['label' => null, 'hint' => null, 'type' => 'text'])

{{--
    A labelled text field. The error comes from the validation bag under the name of the `wire:model` property,
    right under the field (docs/UI.md §5).
--}}
@php
    $model = collect($attributes->getAttributes())->first(fn ($value, $key) => str_starts_with((string) $key, 'wire:model'));
    $id = $attributes->get('id', 'f-'.($model ?? \Illuminate\Support\Str::random(6)));
@endphp

<div class="ui-field">
    @if ($label)
        <label class="ui-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <input id="{{ $id }}" type="{{ $type }}" {{ $attributes->class(['ui-input']) }}>

    @if ($hint)
        <span class="ui-hint">{{ $hint }}</span>
    @endif

    @if ($model)
        @error($model)
            <span class="ui-error">{{ $message }}</span>
        @enderror
    @endif
</div>
