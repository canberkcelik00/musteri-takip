<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body>
    <main class="ui-guest">
        <div class="ui-guest-card ui-glass ui-glass-strong">
            <x-ui.brand />
            {{ $slot }}
        </div>
    </main>
    <x-ui.toast />
    @livewire(\App\Livewire\WindowHost::class)
</body>
</html>
