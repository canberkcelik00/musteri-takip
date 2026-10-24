<div>
    <h1 class="ui-title">{{ __('ui.profile.title') }}</h1>
    <p class="ui-subtitle">{{ __('ui.profile.subtitle') }}</p>

    <div class="mt-6 flex max-w-xl flex-col gap-5">
        <form wire:submit="saveProfile" class="ui-card ui-glass flex flex-col gap-4">
            <h2 class="m-0 text-[17px] font-semibold">{{ __('ui.profile.account') }}</h2>
            <x-ui.input :label="__('ui.profile.name')" wire:model="name" autocomplete="name" required />
            <x-ui.input type="email" :label="__('ui.profile.email')" wire:model="email" autocomplete="email" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.save') }}</x-ui.button>
            </div>
        </form>

        <form wire:submit="changePassword" class="ui-card ui-glass flex flex-col gap-4">
            <h2 class="m-0 text-[17px] font-semibold">{{ __('ui.profile.password_section') }}</h2>
            <x-ui.input type="password" :label="__('ui.profile.current_password')" wire:model="current_password" autocomplete="current-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password')" wire:model="password" autocomplete="new-password" required />
            <x-ui.input type="password" :label="__('ui.profile.new_password_confirmation')" wire:model="password_confirmation" autocomplete="new-password" required />
            <div class="flex justify-end">
                <x-ui.button type="submit">{{ __('ui.profile.change_password') }}</x-ui.button>
            </div>
        </form>
    </div>
</div>
