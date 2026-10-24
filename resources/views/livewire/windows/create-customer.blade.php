<x-ui.window :title="__('customers.create_heading')" submit="save">
    <div class="ui-form">
        <h3 class="ui-form-section is-full">{{ __('customers.customer_section') }}</h3>
        <div class="is-full"><x-ui.input :label="__('customers.short_name')" wire:model="short_name" maxlength="255" required autofocus /></div>
        <div class="is-full"><x-ui.input :label="__('customers.legal_title')" wire:model="legal_title" maxlength="255" /></div>
        <x-ui.input :label="__('customers.tax_office')" wire:model="tax_office" maxlength="255" />
        <x-ui.input :label="__('customers.tax_number')" wire:model="tax_number" maxlength="255" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('customers.billing_address')" wire:model="billing_address" /></div>
        <div class="is-full"><x-ui.input type="textarea" :label="__('customers.notes')" wire:model="notes" /></div>

        <h3 class="ui-form-section is-full">{{ __('customers.first_contact_section') }}</h3>
        <p class="ui-hint is-full" style="margin: -8px 0 0">{{ __('customers.first_contact_helper') }}</p>
        <div class="is-full"><x-ui.input :label="__('contacts.name')" wire:model="contact_name" maxlength="255" required /></div>
        <x-ui.input :label="__('contacts.title')" wire:model="contact_title" maxlength="255" />
        <x-ui.input type="tel" :label="__('contacts.phone')" wire:model="contact_phone" maxlength="255" />
        <div class="is-full"><x-ui.input type="email" :label="__('contacts.email')" wire:model="contact_email" maxlength="255" /></div>
    </div>
</x-ui.window>
