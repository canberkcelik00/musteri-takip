{{-- Shared by the "Ücretsiz yap" and "İptal et" windows: the cancel-the-upcoming-charge box and what stays open. --}}
<x-ui.window :title="$title" submit="save" :submit-label="$submitLabel">
    <div class="ui-form">
        @if ($withCancelFields)
            <div class="is-full"><x-ui.date-input :label="__('customer_services.cancelled_on')" wire:model="cancelled_on" /></div>
            <div class="is-full"><x-ui.input type="textarea" :label="__('customer_services.cancel_note')" wire:model="cancel_note" /></div>
        @endif

        @if ($preview['cancellable'])
            <label class="ui-check is-full">
                <input type="checkbox" wire:model="cancel_upcoming_charges">
                <span>{{ __('customer_services.cancel_upcoming_charges', ['amount' => $preview['cancellable']]) }}</span>
            </label>
        @endif

        @if ($preview['overdue'])
            <p class="ui-note is-full">{{ __('customer_services.stays_overdue', ['amount' => $preview['overdue']]) }}</p>
        @endif

        @if ($preview['open'])
            <p class="ui-note is-full">{{ __('customer_services.stays_open', ['amount' => $preview['open']]) }}</p>
        @endif
    </div>
</x-ui.window>
