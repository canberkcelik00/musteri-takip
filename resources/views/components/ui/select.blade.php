@props(['options' => [], 'label' => null, 'placeholder' => null, 'searchable' => false])

{{--
    A select that is never the system's own (docs/UI.md §6 "Seçim kutusu"): an Alpine listbox with a search field
    (`searchable`), grouped options (`['Group' => ['key' => 'Label']]`) and keyboard use (arrows, Enter, Esc).
    Bind it with wire:model; the value is exchanged as a string.
--}}
@php
    $list = [];

    foreach ($options as $key => $option) {
        if (is_array($option)) {
            foreach ($option as $groupKey => $groupLabel) {
                $list[] = ['value' => (string) $groupKey, 'label' => (string) $groupLabel, 'group' => (string) $key];
            }
        } else {
            $list[] = ['value' => (string) $key, 'label' => (string) $option, 'group' => null];
        }
    }

    $model = collect($attributes->getAttributes())->first(fn ($value, $key) => str_starts_with((string) $key, 'wire:model'));
@endphp

<div class="ui-field">
    @if ($label)
        <span class="ui-label">{{ $label }}</span>
    @endif

    <div
        class="ui-select"
        x-data="{
            value: null,
            open: false,
            query: '',
            active: 0,
            options: @js($list),
            get filtered() {
                const query = this.query.trim().toLocaleLowerCase('tr');
                let previous = null;

                return this.options
                    .filter((option) => query === '' || option.label.toLocaleLowerCase('tr').includes(query))
                    .map((option) => {
                        const heading = option.group !== previous ? option.group : null;
                        previous = option.group;

                        return { ...option, heading };
                    });
            },
            get selected() {
                return this.options.find((option) => option.value === String(this.value ?? ''));
            },
            toggle() {
                this.open = ! this.open;

                if (this.open) {
                    this.query = '';
                    this.active = Math.max(0, this.filtered.findIndex((option) => option.value === String(this.value ?? '')));
                    this.$nextTick(() => this.$refs.search?.focus());
                }
            },
            move(step) {
                if (! this.open) { this.toggle(); return; }
                const count = this.filtered.length;
                if (count > 0) { this.active = (this.active + step + count) % count; }
            },
            pick(option = this.filtered[this.active]) {
                if (! option) { return; }
                this.value = option.value;
                this.open = false;
                this.$refs.trigger.focus();
            },
        }"
        x-modelable="value"
        x-on:keydown.escape.stop="open = false"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:click.outside="open = false"
        {{ $attributes->whereStartsWith('wire:model') }}
    >
        <button type="button" class="ui-select-trigger" x-ref="trigger" x-on:click="toggle()" aria-haspopup="listbox" x-bind:aria-expanded="open">
            <span x-show="selected" x-text="selected?.label"></span>
            <span x-show="! selected" class="ui-select-placeholder">{{ $placeholder ?? __('ui.select.placeholder') }}</span>
            <x-heroicon-m-chevron-up-down aria-hidden="true" />
        </button>

        <div class="ui-select-panel ui-glass ui-glass-menu" x-show="open" x-cloak role="listbox">
            @if ($searchable)
                <input
                    type="text"
                    class="ui-input ui-select-search"
                    x-ref="search"
                    x-model="query"
                    x-on:input="active = 0"
                    x-on:keydown.enter.prevent="pick()"
                    placeholder="{{ __('ui.select.search') }}"
                    aria-label="{{ __('ui.select.search') }}"
                >
            @else
                <span x-ref="search" x-on:keydown.enter.prevent="pick()" tabindex="-1"></span>
            @endif

            <template x-for="(option, index) in filtered" x-bind:key="option.value">
                <div>
                    <div class="ui-option-group" x-show="option.heading" x-text="option.heading"></div>
                    <button
                        type="button"
                        role="option"
                        class="ui-option"
                        x-bind:class="{ 'is-active': index === active, 'is-selected': option.value === String(value ?? '') }"
                        x-bind:aria-selected="option.value === String(value ?? '')"
                        x-on:mouseenter="active = index"
                        x-on:click="pick(option)"
                        x-text="option.label"
                    ></button>
                </div>
            </template>

            <div class="ui-option-empty" x-show="filtered.length === 0">{{ __('ui.select.empty') }}</div>
        </div>
    </div>

    @if ($model)
        @error($model)
            <span class="ui-error">{{ $message }}</span>
        @enderror
    @endif
</div>
