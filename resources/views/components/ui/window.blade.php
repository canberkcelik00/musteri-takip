@props(['title', 'submit' => null, 'submitLabel' => null, 'size' => null])

{{--
    A centered glass window over a dimmed layer. Rendered inside WindowHost (end of body), so no bar can trap it.
    Esc or a click on the layer closes it. With `submit` (a Livewire action) the window is a form and its footer is
    "Vazgeç" on the left and the one primary button on the right (docs/UI.md §5); a `footer` slot replaces that.
--}}
@php
    $tag = $submit ? 'form' : 'div';
@endphp

<div
    class="ui-window-layer"
    x-data
    x-on:keydown.escape.window="$dispatch('close-window')"
    x-on:mousedown.self="$dispatch('close-window')"
>
    <{{ $tag }}
        class="ui-window ui-glass-menu"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $title }}"
        x-trap.noscroll="true"
        @if ($submit) wire:submit="{{ $submit }}" @endif
    >
        <div @class(['ui-window-header', 'has-close' => $submit])>
            {{ $title }}
            @if ($submit)
                <x-ui.button size="sm" class="ui-window-close" x-on:click="$dispatch('close-window')">{{ __('actions.close') }}</x-ui.button>
            @endif
        </div>
        <div class="ui-window-body">
            @if (isset($errors) && $errors->has('form'))
                <div class="ui-error-box ui-mb-3" role="alert">{{ $errors->first('form') }}</div>
            @endif
            {{ $slot }}
        </div>
        @if (isset($footer))
            <div class="ui-window-footer">{{ $footer }}</div>
        @elseif ($submit)
            <div class="ui-window-footer">
                <x-ui.button x-on:click="$dispatch('close-window')">{{ __('actions.discard') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary">{{ $submitLabel ?? __('actions.save') }}</x-ui.button>
            </div>
        @endif
    </{{ $tag }}>
</div>
