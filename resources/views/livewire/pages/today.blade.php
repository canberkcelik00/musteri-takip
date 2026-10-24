<div>
    <h1 class="ui-title">{{ __('ui.nav.today') }}</h1>

    <div class="ui-card ui-glass" style="margin-top: 22px">
        <p style="margin: 0 0 14px">{{ __('ui.today.placeholder') }}</p>
        <x-ui.button :href="route('filament.panel.pages.dashboard')">{{ __('ui.today.open_panel') }}</x-ui.button>
    </div>
</div>
