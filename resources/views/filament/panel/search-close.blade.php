{{-- The "Kapat" button of the narrow-screen search (see search-open): inside the search container, beside the field; hidden from `sm` up. --}}
<x-filament::button
    color="gray"
    size="sm"
    type="button"
    x-data
    x-on:click="$dispatch('close-topbar-search')"
    class="fi-search-close"
>
    {{ __('navigation.search_close') }}
</x-filament::button>
