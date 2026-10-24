@php
    $user = auth()->user();
@endphp
<div
    x-data="{
        theme: 'light',
        glass: 'on',
        init() {
            try {
                this.theme = localStorage.getItem('mt-theme') === 'dark' ? 'dark' : 'light';
                this.glass = localStorage.getItem('mt-glass') === 'off' ? 'off' : 'on';
            } catch (e) {}

            window.mtApplyAppearance();

            this.$watch('theme', (value) => {
                try { localStorage.setItem('mt-theme', value); } catch (e) {}
                window.mtApplyAppearance();
            });
            this.$watch('glass', (value) => {
                try { localStorage.setItem('mt-glass', value); } catch (e) {}
                window.mtApplyAppearance();
            });
        },
    }"
>
    <x-ui.dropdown align="right">
        <x-slot:trigger>
            <button type="button" class="ui-avatar ui-avatar-user" aria-label="{{ __('ui.user_menu.label') }}" aria-haspopup="menu" x-on:click="toggle()" x-bind:aria-expanded="open">
                {{ \App\Support\Avatar::initials($user?->name ?? '') }}
            </button>
        </x-slot:trigger>

        <div class="ui-dropdown-label">{{ $user?->name }}</div>
        <div class="ui-dropdown-label">{{ $user?->email }}</div>
        <div class="ui-dropdown-divider"></div>

        <a href="{{ route('app.profile') }}" class="ui-dropdown-item">{{ __('ui.user_menu.profile') }}</a>

        <div class="ui-dropdown-row">
            <span>{{ __('ui.user_menu.theme') }}</span>
            <x-ui.segmented :options="['light' => __('ui.user_menu.theme_light'), 'dark' => __('ui.user_menu.theme_dark')]" x-model="theme" />
        </div>
        <div class="ui-dropdown-row">
            <span>{{ __('ui.user_menu.glass') }}</span>
            <x-ui.segmented :options="['on' => __('ui.user_menu.glass_on'), 'off' => __('ui.user_menu.glass_off')]" x-model="glass" />
        </div>

        <div class="ui-dropdown-divider"></div>

        <form method="POST" action="{{ route('app.logout') }}">
            @csrf
            <button type="submit" class="ui-dropdown-item">{{ __('ui.user_menu.logout') }}</button>
        </form>
    </x-ui.dropdown>
</div>
