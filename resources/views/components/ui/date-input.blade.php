@props(['label' => null])

{{--
    A date field that shows and accepts dd.mm.yyyy and keeps the value as yyyy-mm-dd; a month grid opens on focus.
    Bind it with wire:model.
--}}
@php
    $model = collect($attributes->getAttributes())->first(fn ($value, $key) => str_starts_with((string) $key, 'wire:model'));
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
            year: 0,
            month: 0,
            months: @js(__('ui.date.months')),
            days: @js(__('ui.date.days')),
            valid(iso) { return /^\d{4}-\d{2}-\d{2}$/.test(iso || ''); },
            format(iso) { if (! this.valid(iso)) { return ''; } const [y, m, d] = iso.split('-'); return d + '.' + m + '.' + y; },
            iso(y, m, d) { return String(y).padStart(4, '0') + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0'); },
            show(iso) {
                const base = this.valid(iso) ? new Date(iso + 'T00:00:00') : new Date();
                this.year = base.getFullYear();
                this.month = base.getMonth();
            },
            typed() {
                const match = this.text.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);

                if (match) {
                    const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));

                    if (date.getMonth() === Number(match[2]) - 1) {
                        this.value = this.iso(date.getFullYear(), date.getMonth(), date.getDate());
                    }
                } else if (this.text === '') {
                    this.value = '';
                }
            },
            cells() {
                const offset = (new Date(this.year, this.month, 1).getDay() + 6) % 7;
                const count = new Date(this.year, this.month + 1, 0).getDate();

                return [...Array(offset).fill(null), ...Array.from({ length: count }, (_, index) => index + 1)];
            },
            shift(step) {
                const date = new Date(this.year, this.month + step, 1);
                this.year = date.getFullYear();
                this.month = date.getMonth();
            },
            pick(day) { this.value = this.iso(this.year, this.month, day); this.open = false; },
            place() {
                const panel = this.$refs.panel;
                const rect = this.$refs.input.getBoundingClientRect();
                const flip = window.innerHeight - rect.bottom < panel.offsetHeight + 12 && rect.top > window.innerHeight - rect.bottom;

                panel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8)) + 'px';
                panel.style.top = flip ? 'auto' : rect.bottom + 6 + 'px';
                panel.style.bottom = flip ? window.innerHeight - rect.top + 6 + 'px' : 'auto';
            },
            dismissOnScroll(event) {
                if (this.open && ! this.$refs.panel.contains(event.target)) { this.open = false; }
            },
            today(day) { const now = new Date(); return this.year === now.getFullYear() && this.month === now.getMonth() && day === now.getDate(); },
            init() {
                this.$watch('value', (iso) => { this.text = this.format(iso); this.show(iso); });
                this.text = this.format(this.value);
                this.show(this.value);
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
            placeholder="gg.aa.yyyy"
            autocomplete="off"
            x-model="text"
            x-on:input="typed()"
            x-on:focus="open = true"
            x-on:blur="text = format(value)"
        >

        <div class="ui-date-panel ui-glass ui-glass-menu" x-ref="panel" x-show="open" x-cloak>
            <div class="ui-date-head">
                <button type="button" class="ui-btn ui-btn-icon ui-btn-sm" x-on:click="shift(-1)" aria-label="{{ __('ui.date.previous') }}">
                    <x-heroicon-m-chevron-left aria-hidden="true" />
                </button>
                <span x-text="months[month] + ' ' + year"></span>
                <button type="button" class="ui-btn ui-btn-icon ui-btn-sm" x-on:click="shift(1)" aria-label="{{ __('ui.date.next') }}">
                    <x-heroicon-m-chevron-right aria-hidden="true" />
                </button>
            </div>
            <div class="ui-date-grid">
                <template x-for="name in days" x-bind:key="name"><span class="ui-date-dow" x-text="name"></span></template>
                <template x-for="(day, index) in cells()" x-bind:key="index">
                    <span>
                        <button
                            type="button"
                            class="ui-date-day"
                            x-show="day"
                            x-text="day"
                            x-bind:class="{ 'is-selected': day && value === iso(year, month, day), 'is-today': day && today(day) }"
                            x-on:click="pick(day)"
                        ></button>
                    </span>
                </template>
            </div>
        </div>
    </div>

    @if ($model)
        @error($model)
            <span class="ui-error">{{ $message }}</span>
        @enderror
    @endif
</div>
