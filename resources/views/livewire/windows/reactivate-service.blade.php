<x-ui.window :title="__('customer_services.reactivate_heading')" submit="save" :submit-label="__('customer_services.reactivate')">
    <div class="ui-form">
        @if ($service->customer->isArchived())
            <p class="ui-note is-full">{{ __('customer_services.customer_archived') }}</p>
        @endif

        @if ($periodEnded)
            <p class="ui-note is-full">{{ __('customer_services.reactivate_period_ended_helper') }}</p>
            <div class="is-full"><x-ui.date-input :label="__('customer_services.reactivate_new_start_date')" wire:model.live="start_date" /></div>
        @endif

        @if ($canCreateFirstCharge)
            <div class="ui-field is-full">
                <span class="ui-label">{{ __('customer_services.create_first_charge') }}</span>
                <div class="ui-radio">
                    <label><input type="radio" wire:model="create_first_charge" value="1"> {{ __('customer_services.create_first_charge_yes') }}</label>
                    <label><input type="radio" wire:model="create_first_charge" value="0"> {{ __('customer_services.create_first_charge_no') }}</label>
                </div>
            </div>
        @endif

        @if ($canReopen)
            <label class="ui-check is-full">
                <input type="checkbox" wire:model="reopen_upcoming_charge">
                <span>{{ __('customer_services.reopen_charge', ['amount' => $reopenAmount]) }}</span>
            </label>
        @endif
    </div>
</x-ui.window>
