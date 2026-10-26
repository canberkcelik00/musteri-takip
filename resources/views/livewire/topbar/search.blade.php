@php
    use App\Support\Ui\Links;

    $results = $this->results;
    $filled = mb_strlen(trim($query)) >= \App\Livewire\Topbar\Search::MIN_LENGTH;
@endphp

{{--
    Below `sm` the field gives way to a text "Ara" button; it opens the search over the whole top bar with a "Kapat"
    beside it (docs/UI.md §3). Ctrl+K (⌘K) opens and focuses it on any width. The x-data stays the same on every
    render: Alpine re-creates a component whose x-data attribute changes, which would close the results.
--}}
<div
    class="ui-search"
    x-data="{ expanded: false, open: false, filled: false }"
    x-bind:class="{ 'is-expanded': expanded }"
    x-on:keydown.window.ctrl.k.prevent="expanded = true; open = true; $nextTick(() => $refs.field.focus())"
    x-on:keydown.window.meta.k.prevent="expanded = true; open = true; $nextTick(() => $refs.field.focus())"
    x-on:keydown.escape="open = false; expanded = false"
    x-on:click.outside="open = false"
>
    <x-ui.button class="ui-search-open" x-on:click="expanded = true; open = true; $nextTick(() => $refs.field.focus())">{{ __('navigation.search_open') }}</x-ui.button>

    <div class="ui-search-field ui-search-input">
        <x-heroicon-m-magnifying-glass class="ui-search-icon" aria-hidden="true" />
        <input
            type="search"
            class="ui-input ui-input-search"
            x-ref="field"
            wire:model.live.debounce.250ms="query"
            x-on:input="filled = $el.value !== ''; open = true"
            x-on:focus="open = true"
            x-on:keydown.arrow-down.prevent="$refs.results && $focus.within($refs.results).first()"
            placeholder="{{ __('ui.search.placeholder') }}"
            aria-label="{{ __('ui.search.label') }}"
            autocomplete="off"
        >
        <button
            type="button"
            class="ui-search-clear"
            x-show="filled"
            x-cloak
            aria-label="{{ __('ui.search.clear') }}"
            x-on:click="$refs.field.value = ''; $refs.field.dispatchEvent(new Event('input', { bubbles: true })); $refs.field.focus()"
        >
            <x-heroicon-s-x-circle aria-hidden="true" />
        </button>

        @if ($filled)
            <div class="ui-search-results ui-glass ui-glass-menu" x-ref="results" x-show="open" x-cloak>
                @forelse ($results as $customer)
                    <div
                        class="ui-search-result"
                        wire:key="result-{{ $customer->id }}"
                        x-on:keydown.arrow-down.prevent="$focus.wrap().next()"
                        x-on:keydown.arrow-up.prevent="$focus.wrap().previous()"
                    >
                        <a href="{{ Links::customer($customer) }}">
                            <x-ui.avatar :name="$customer->short_name" :id="$customer->id" />
                            <span class="min-w-0">
                                <span class="ui-cell-title block">{{ $customer->short_name }}</span>
                                @php
                                    $details = array_filter([
                                        $customer->legal_title,
                                        implode(', ', array_slice($customer->domains(), 0, 3)),
                                        $customer->isArchived() ? __('archive.status_archived') : null,
                                    ]);
                                @endphp
                                @if ($details !== [])
                                    <span class="ui-cell-sub block">{{ implode(' · ', $details) }}</span>
                                @endif
                            </span>
                        </a>
                        @unless ($customer->isArchived())
                            <x-ui.button
                                size="sm"
                                x-on:click="open = false; expanded = false; $dispatch('open-window', { name: 'record-payment', params: { customer: {{ $customer->id }} } })"
                            >{{ __('payments.record') }}</x-ui.button>
                        @endunless
                    </div>
                @empty
                    <div class="ui-option-empty">{{ __('ui.search.empty') }}</div>
                @endforelse
            </div>
        @endif
    </div>

    <x-ui.button class="ui-search-close" x-on:click="expanded = false; open = false">{{ __('navigation.search_close') }}</x-ui.button>
</div>
