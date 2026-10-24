<x-ui.window :title="__('charges.split_heading')" submit="save" :submit-label="__('charges.split')">
    <div class="ui-form">
        <p class="ui-note is-full">{{ __('charges.split_helper') }}</p>
        <div class="is-full"><x-ui.money-input :label="__('charges.split_amount')" wire:model="amount" :suffix="$charge->currency->getLabel()" autofocus /></div>
        <div class="is-full"><x-ui.date-input :label="__('charges.split_due_date')" wire:model="due_date" /></div>
        <div class="is-full"><x-ui.input :label="__('charges.split_description')" wire:model="description" :placeholder="$charge->description" maxlength="255" /></div>
    </div>
</x-ui.window>
