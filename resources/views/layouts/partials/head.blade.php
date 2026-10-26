{{--
    The document head shared by both layouts. The inline script runs before the first paint and applies the saved
    theme ("Açık" is the default) and the saved glass choice, so a reload never flashes the other look.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ __('ui.brand_name') }}</title>
<script>
    window.mtApplyAppearance = function () {
        try {
            var root = document.documentElement;
            root.classList.toggle('dark', localStorage.getItem('mt-theme') === 'dark');
            root.classList.toggle('no-glass', localStorage.getItem('mt-glass') === 'off');
        } catch (e) {}
    };
    window.mtApplyAppearance();
    // wire:navigate swaps the page without a reload: re-apply right after the swap (no flash) and once navigated.
    document.addEventListener('livewire:navigating', function (event) {
        event.detail.onSwap(window.mtApplyAppearance);
    });
    document.addEventListener('livewire:navigated', window.mtApplyAppearance);
</script>
{{-- The latin Inter subset is on almost every page: fetch it with the CSS instead of after it (fonts need crossorigin). --}}
<link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2') }}">
@vite('resources/css/ui.css')
