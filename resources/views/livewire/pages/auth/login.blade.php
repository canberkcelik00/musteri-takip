<form wire:submit="login" class="flex flex-col gap-4">
    <x-ui.input type="email" :label="__('ui.login.email')" wire:model="email" autocomplete="username" autofocus required />
    <x-ui.input type="password" :label="__('ui.login.password')" wire:model="password" autocomplete="current-password" required />

    <label class="ui-check">
        <input type="checkbox" wire:model="remember">
        <span>{{ __('ui.login.remember') }}</span>
    </label>

    <x-ui.button type="submit" variant="primary">{{ __('ui.login.submit') }}</x-ui.button>
</form>
