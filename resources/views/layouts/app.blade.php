@php
    use App\Support\Ui\Navigation;

    $tabs = Navigation::tabs();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body>
    <div x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
        @include('layouts.partials.sidebar')

        <div class="ui-scrim" x-show="menu" x-cloak x-on:click="menu = false"></div>

        <div class="ui-main">
            <header class="ui-topbar ui-glass">
                @livewire(\App\Livewire\Topbar\Search::class)
                <div class="flex-1"></div>
                <div class="ui-topbar-actions">
                    <x-ui.button variant="primary" x-on:click="$dispatch('open-window', { name: 'record-payment' })">{{ __('payments.record') }}</x-ui.button>
                    <x-ui.button class="ui-topbar-new" x-on:click="$dispatch('open-window', { name: 'create-customer' })">{{ __('navigation.new_customer') }}</x-ui.button>
                </div>
                @include('layouts.partials.user-menu')
            </header>

            <main class="ui-page">
                {{ $slot }}
            </main>
        </div>

        <nav class="ui-tabbar ui-glass" aria-label="{{ __('ui.nav.tab_bar') }}">
            @foreach ($tabs as $tab)
                <a href="{{ $tab['url'] }}" class="ui-tab" @if ($tab['active']) aria-current="page" @endif>
                    <x-dynamic-component :component="'heroicon-o-'.$tab['icon']" aria-hidden="true" />
                    <span>{{ __($tab['tab']) }}</span>
                </a>
            @endforeach
            <button type="button" class="ui-tab" x-on:click="menu = true">
                <x-heroicon-o-bars-3 aria-hidden="true" />
                <span>{{ __('ui.nav.tab_menu') }}</span>
            </button>
        </nav>
    </div>

    <x-ui.toast />
    @livewire(\App\Livewire\WindowHost::class)
</body>
</html>
