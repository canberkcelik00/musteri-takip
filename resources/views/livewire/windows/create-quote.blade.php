<x-ui.window :title="__('quotes.create_heading')" submit="save">
    <div class="ui-form">
        @if ($quoted === '')
            <div class="is-full"><x-ui.select :label="__('quotes.sold')" wire:model="sold" :options="$catalog" :searchable="true" /></div>
        @endif

        <div class="is-full">
            <x-ui.select :label="__('quotes.customer')" wire:model.live="customer_id" :options="['' => '—'] + $customers" :searchable="true" />
            <span class="ui-hint">{{ __('quotes.customer_helper') }}</span>
        </div>

        @if ($customer_id === '')
            <x-ui.input :label="__('quotes.recipient_name')" wire:model="recipient_name" maxlength="255" />
            <x-ui.input :label="__('quotes.recipient_company')" wire:model="recipient_company" maxlength="255" />
            <x-ui.input type="email" :label="__('quotes.recipient_email')" wire:model="recipient_email" maxlength="255" />
            <x-ui.input type="tel" :label="__('quotes.recipient_phone')" wire:model="recipient_phone" maxlength="255" />
        @endif

        <div class="is-full"><x-ui.input type="textarea" :label="__('quotes.notes')" wire:model="notes" /></div>
    </div>
</x-ui.window>
