@props(['label' => null, 'step' => 15])

{{--
    A time field that shows and accepts hh:mm and keeps the value as HH:MM; a list of times (every `step` minutes)
    opens on focus. Bind it with wire:model.
--}}
@php
    $model = collect($attributes->getAttributes())->first(fn ($value, $key) => str_starts_with((string) $key, 'wire:model'));
    $slots = [];

    for ($minute = 0; $minute < 1440; $minute += max($step, 1)) {
        $slots[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
@endphp

<div class="ui-field">
    @if ($label)
        <span class="ui-label">{{ $label }}</span>
    @endif

    <div
        class="ui-date"
        x-data="{
            value: '',
            text: '',
            open: false,
            slots: @js($slots),
            normalize(input) {
                const match = String(input || '').trim().match(/^(\d{1,2})(?:[:.]?(\d{2}))?$/);

                if (! match) { return null; }

                const hour = Number(match[1]);
                const minute = Number(match[2] ?? 0);

                return hour < 24 && minute < 60 ? String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0') : null;
            },
            typed() {
                const time = this.normalize(this.text);

                if (time !== null) { this.value = time; } else if (this.text === '') { this.value = ''; }
            },
            pick(slot) { this.value = slot; this.open = false; },
            place() {
                const panel = this.$refs.panel;
                const rect = this.$refs.input.getBoundingClientRect();
                const flip = window.innerHeight - rect.bottom < panel.offsetHeight + 12 && rect.top > window.innerHeight - rect.bottom;

                panel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8)) + 'px';
                panel.style.minWidth = rect.width + 'px';
                panel.style.top = flip ? 'auto' : rect.bottom + 6 + 'px';
                panel.style.bottom = flip ? window.innerHeight - rect.top + 6 + 'px' : 'auto';

                this.$nextTick(() => panel.querySelector('.is-selected')?.scrollIntoView({ block: 'center' }));
            },
            dismissOnScroll(event) {
                if (this.open && ! this.$refs.panel.contains(event.target)) { this.open = false; }
            },
            init() {
                this.$watch('value', (time) => { this.text = time || ''; });
                this.text = this.value || '';
            },
        }"
        x-modelable="value"
        x-on:keydown.escape.stop="open = false"
        x-on:click.outside="open = false"
        x-on:resize.window="open && place()"
        x-on:scroll.window.capture="dismissOnScroll($event)"
        x-effect="open && $nextTick(() => place())"
        {{ $attributes->whereStartsWith('wire:model') }}
    >
        <input
            type="text"
            class="ui-input"
            x-ref="input"
            inputmode="numeric"
            placeholder="ss:dd"
            autocomplete="off"
            x-model="text"
            x-on:input="typed()"
            x-on:focus="open = true"
            x-on:blur="text = value || ''"
        >

        <div class="ui-date-panel ui-time-panel ui-glass ui-glass-menu" x-ref="panel" x-show="open" x-cloak role="listbox">
            <template x-for="slot in slots" x-bind:key="slot">
                <button
                    type="button"
                    role="option"
                    class="ui-option"
                    x-bind:class="{ 'is-selected': slot === value }"
                    x-bind:aria-selected="slot === value"
                    x-on:click="pick(slot)"
                    x-text="slot"
                ></button>
            </template>
        </div>
    </div>

    @if ($model)
        @error($model)
            <span class="ui-error">{{ $message }}</span>
        @enderror
    @endif
</div>
