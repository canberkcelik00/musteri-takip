<x-ui.window :title="__('customer_services.remove_free_heading')" submit="save">
    <x-ui.money-input :label="__('customer_services.new_price')" wire:model="price" :suffix="$service->currency->getLabel()" autofocus />
</x-ui.window>
