@if ($invalid)
    <div class="flex flex-col gap-4">
        <p>{{ __('users.invitation_invalid') }}</p>
        <x-ui.link-button :href="route('app.login')">{{ __('users.go_to_login') }}</x-ui.link-button>
    </div>
@else
    <form wire:submit="accept" class="flex flex-col gap-4">
        <p>{{ __('users.accept_intro') }}</p>
        <x-ui.input type="password" :label="__('users.password')" wire:model="password" autocomplete="new-password" autofocus required />
        <x-ui.input type="password" :label="__('users.password_confirmation')" wire:model="password_confirmation" autocomplete="new-password" required />

        <x-ui.button type="submit" variant="primary">{{ __('users.accept_submit') }}</x-ui.button>
    </form>
@endif
