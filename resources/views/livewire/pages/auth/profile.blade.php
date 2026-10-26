<div>
    <x-ui.page-header :title="__('ui.profile.title')" :subtitle="__('ui.profile.subtitle')" />

    <div class="mt-6 flex max-w-xl flex-col gap-5">
        <x-ui.card tag="form" wire:submit="saveProfile" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('ui.profile.account') }}</h2></div>
            <x-ui.input :label="__('ui.profile.name')" wire:model="name" autocomplete="name" required />
            <x-ui.input type="email" :label="__('ui.profile.email')" wire:model="email" autocomplete="email" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.save') }}</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card tag="form" wire:submit="changePassword" class="flex flex-col gap-4">
            <div class="ui-card-head"><h2>{{ __('ui.profile.password_section') }}</h2></div>
            <x-ui.input type="password" :label="__('ui.profile.current_password')" wire:model="current_password" autocomplete="current-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password')" wire:model="password" autocomplete="new-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password_confirmation')" wire:model="password_confirmation" autocomplete="new-password" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.change_password') }}</x-ui.button>
            </div>
        </x-ui.card>
    </div>
</div>
