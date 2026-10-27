@php
    use App\Support\Ui\Links;

    $filled = mb_strlen(trim($query)) >= \App\Livewire\Topbar\Search::MIN_LENGTH;
    $customers = $filled ? $this->results : $this->recents;
    $pages = $this->pages;
    $showBox = $filled || $customers->isNotEmpty();
@endphp

{{--
    Below `sm` the field gives way to a text "Ara" button; it opens the search over the whole top bar with a "Kapat"
    beside it (docs/UI.md §3). Ctrl+K (⌘K) opens and focuses it on any width. The x-data stays the same on every
    render: Alpine re-creates a component whose x-data attribute changes, which would close the results.
--}}
<div
    class="ui-search"
    x-data="{ expanded: false, open: false }"
    x-bind:class="{ 'is-expanded': expanded }"
    x-on:keydown.window.ctrl.k.prevent="expanded = true; open = true; $nextTick(() => $root.querySelector('input[type=search]').focus())"
    x-on:keydown.window.meta.k.prevent="expanded = true; open = true; $nextTick(() => $root.querySelector('input[type=search]').focus())"
    x-on:keydown.escape="open = false; expanded = false"
    x-on:click.outside="open = false"
>
    <x-ui.button class="ui-search-open" x-on:click="expanded = true; open = true; $nextTick(() => $root.querySelector('input[type=search]').focus())">{{ __('navigation.search_open') }}</x-ui.button>

    <x-ui.search-field
        wrapper-class="ui-search-field"
        wire:model.live.debounce.250ms="query"
        x-on:input="open = true"
        x-on:focus="open = true"
        x-on:keydown.arrow-down.prevent="$refs.results && $focus.within($refs.results).first()"
        placeholder="{{ __('ui.search.placeholder') }}"
        aria-label="{{ __('ui.search.label') }}"
        autocomplete="off"
    >
        @if ($showBox)
            <div class="ui-search-results ui-glass ui-glass-menu" x-ref="results" x-show="open" x-cloak>
                @foreach ($pages as $page)
                    <div
                        class="ui-search-result"
                        wire:key="page-{{ $loop->index }}"
                        x-on:keydown.arrow-down.prevent="$focus.wrap().next()"
                        x-on:keydown.arrow-up.prevent="$focus.wrap().previous()"
                    >
                        <a href="{{ $page['url'] }}" wire:navigate>
                            <span class="ui-cell-title">{{ __('ui.search.go', ['page' => $page['label']]) }}</span>
                        </a>
                    </div>
                @endforeach

                @unless ($filled)
                    <div class="ui-option-empty">{{ __('ui.search.recent') }}</div>
                @endunless

                @forelse ($customers as $customer)
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
                                        $customer->relationLoaded('customerServices') ? implode(', ', array_slice($customer->domains(), 0, 3)) : null,
                                        $customer->isArchived() ? __('archive.status_archived') : null,
                                    ]);
                                @endphp
                                @if ($details !== [])
                                    <span class="ui-cell-sub block">{{ implode(' · ', $details) }}</span>
                                @endif
                            </span>
                        </a>
                        @unless ($customer->isArchived())
                            @can('money.manage')
                                <x-ui.button
                                    size="sm"
                                    x-on:click="open = false; expanded = false; $dispatch('open-window', { name: 'record-payment', params: { customer: {{ $customer->id }} } })"
                                >{{ __('payments.record') }}</x-ui.button>
                            @elsecan('tasks.manage')
                                <x-ui.button
                                    size="sm"
                                    x-on:click="open = false; expanded = false; $dispatch('open-window', { name: 'edit-task', params: { customer: {{ $customer->id }} } })"
                                >{{ __('tasks.add') }}</x-ui.button>
                            @endcan
                        @endunless
                    </div>
                @empty
                    @if ($filled && $pages === [])
                        <div class="ui-option-empty">{{ __('ui.search.empty') }}</div>
                    @endif
                @endforelse
            </div>
        @endif
    </x-ui.search-field>

    <x-ui.button class="ui-search-close" x-on:click="expanded = false; open = false">{{ __('navigation.search_close') }}</x-ui.button>
</div>
