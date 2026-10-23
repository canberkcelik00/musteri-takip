{{--
    The bottom tab bar of narrow screens (G-4C.3, docs/UI.md §3 "Dar ekranda"): the three daily screens and
    "Menü", which opens the full sidebar. Shown below `lg` only (the theme hides it above); on a wide screen the
    sidebar carries the same items. The current screen's tab is marked with aria-current, which the theme paints.
--}}
@php
    use Filament\Support\Icons\Heroicon;

    $tabs = [
        ['label' => __('navigation.tab_today'), 'url' => \App\Filament\Pages\Dashboard::getUrl(), 'icon' => Heroicon::OutlinedCalendarDays, 'active' => request()->routeIs('filament.panel.pages.dashboard')],
        ['label' => __('navigation.tab_customers'), 'url' => \App\Filament\Resources\Customers\CustomerResource::getUrl(), 'icon' => Heroicon::OutlinedUsers, 'active' => request()->routeIs('filament.panel.resources.customers.*')],
        ['label' => __('navigation.tab_charges'), 'url' => \App\Filament\Resources\Charges\ChargeResource::getUrl(), 'icon' => Heroicon::OutlinedReceiptPercent, 'active' => request()->routeIs('filament.panel.resources.charges.*')],
    ];
@endphp

<nav class="fi-tab-bar" aria-label="{{ __('navigation.tab_bar') }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}" class="fi-tab-bar-item" @if ($tab['active']) aria-current="page" @endif>
            <x-filament::icon :icon="$tab['icon']" class="fi-tab-bar-icon" />
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach

    <button type="button" class="fi-tab-bar-item" x-on:click="$store.sidebar.open()">
        <x-filament::icon :icon="Heroicon::OutlinedBars3" class="fi-tab-bar-icon" />
        <span>{{ __('navigation.tab_menu') }}</span>
    </button>
</nav>
