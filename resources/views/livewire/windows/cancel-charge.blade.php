<x-ui.window :title="__('charges.cancel_heading')" submit="save" :submit-label="__('charges.cancel')">
    <x-ui.input type="textarea" :label="__('charges.cancel_reason')" wire:model="reason" required autofocus />
</x-ui.window>
