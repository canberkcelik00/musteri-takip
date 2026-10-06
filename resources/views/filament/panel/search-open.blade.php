{{--
    The topbar's "Ara" button below `sm` (G-4B.7, docs/UI.md §2 Kural 5): the search box is hidden there, this text
    button opens it over the whole topbar. The open state is the class `fi-topbar-search-open` on the topbar, which the
    theme turns into the overlay; "Kapat" (search-close) and Esc close it. The search container gets the id the button
    points at (`aria-controls`); a Livewire re-render of the container drops an id added from outside, so it is set again.
--}}
<div
    class="fi-search-open"
    x-data="{
        expanded: false,
        topbar: null,
        open() {
            this.expanded = true
            this.topbar.classList.add('fi-topbar-search-open')
            this.$nextTick(() => document.querySelector('.fi-global-search-field input')?.focus())
        },
        close() {
            this.expanded = false
            this.topbar.classList.remove('fi-topbar-search-open')
            this.$nextTick(() => this.$refs.trigger.focus())
        },
        bindContainer() {
            const container = document.querySelector('.fi-global-search-ctn')

            if (! container) {
                return
            }

            container.id = 'fi-topbar-search'

            new MutationObserver(() => {
                if (container.id !== 'fi-topbar-search') {
                    container.id = 'fi-topbar-search'
                }
            }).observe(container, { attributes: true, attributeFilter: ['id'] })
        },
    }"
    x-init="topbar = $el.closest('.fi-topbar'); bindContainer()"
    x-on:close-topbar-search.window="expanded && close()"
    x-on:keydown.escape.window="expanded && close()"
>
    <x-filament::button
        color="gray"
        size="sm"
        :icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass"
        type="button"
        x-ref="trigger"
        x-on:click="open()"
        aria-controls="fi-topbar-search"
        aria-expanded="false"
        x-bind:aria-expanded="expanded.toString()"
    >
        {{ __('navigation.search_open') }}
    </x-filament::button>
</div>
