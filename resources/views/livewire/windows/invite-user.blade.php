@php
    use App\Actions\Users\SendInvitation;
@endphp

<x-ui.window :title="__('users.invite')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('users.name')" wire:model="name" maxlength="255" required autofocus /></div>
        <div class="is-full"><x-ui.input type="email" :label="__('users.email')" wire:model="email" maxlength="255" required /></div>
        <div class="is-full"><x-ui.select :label="__('users.role')" wire:model="role" :options="$roles" /></div>
        <p class="ui-hint is-full">{{ __('users.invite_hint', ['hours' => SendInvitation::VALID_HOURS]) }}</p>
    </div>
</x-ui.window>
