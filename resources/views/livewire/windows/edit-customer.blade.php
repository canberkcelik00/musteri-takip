<x-ui.window :title="__('customers.edit_heading')" submit="save">
    <div class="ui-form">
        <div class="is-full"><x-ui.input :label="__('customers.short_name')" wire:model="short_name" maxlength="255" required /></div>
        <div class="is-full"><x-ui.input :label="__('customers.legal_title')" wire:model="legal_title" maxlength="255" /></div>
        <x-ui.input :label="__('customers.tax_office')" wire:model="tax_office" maxlength="255" />
        <x-ui.input :label="__('customers.tax_number')" wire:model="tax_number" maxlength="255" />
        <div class="is-full"><x-ui.input type="textarea" :label="__('customers.billing_address')" wire:model="billing_address" /></div>
        <div class="is-full"><x-ui.input type="textarea" :label="__('customers.notes')" wire:model="notes" /></div>
    </div>
</x-ui.window>
