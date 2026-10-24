{{--
    The document head shared by both layouts. The inline script runs before the first paint and applies the saved
    theme ("Açık" is the default) and the saved glass choice, so a reload never flashes the other look.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ __('ui.brand_name') }}</title>
<script>
    try {
        var root = document.documentElement;
        root.classList.toggle('dark', localStorage.getItem('mt-theme') === 'dark');
        root.classList.toggle('no-glass', localStorage.getItem('mt-glass') === 'off');
    } catch (e) {}
</script>
@vite('resources/css/ui.css')
