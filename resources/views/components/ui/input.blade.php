@props(['label' => null, 'hint' => null, 'type' => 'text', 'suffix' => null])

{{--
    A labelled field: a text input, or a textarea with type="textarea". The error comes from the validation bag under
    the name of the `wire:model` property, right under the field (docs/UI.md §5). `suffix` shows a unit (a currency).
--}}
@php
    $model = collect($attributes->getAttributes())->first(fn ($value, $key) => str_starts_with((string) $key, 'wire:model'));
    $id = $attributes->get('id', 'f-'.($model ?? \Illuminate\Support\Str::random(6)));
@endphp

<div class="ui-field">
    @if ($label)
        <label class="ui-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div @class(['ui-input-suffix' => $suffix])>
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" {{ $attributes->class(['ui-input', 'ui-textarea']) }}></textarea>
        @elseif ($type === 'search')
            <x-ui.search-field :id="$id" :attributes="$attributes" />
        @else
            <input id="{{ $id }}" type="{{ $type }}" {{ $attributes->class(['ui-input']) }}>
        @endif

        @if ($suffix)
            <span>{{ $suffix }}</span>
        @endif
    </div>

    @if ($hint)
        <span class="ui-hint">{{ $hint }}</span>
    @endif

    @if ($model)
        @error($model)
            <span class="ui-error">{{ $message }}</span>
        @enderror
    @endif
</div>
