<x-ui.window :title="__('quotes.accept_heading')" submit="save" :submit-label="__('quotes.accept')">
    <div class="ui-form">
        <p class="ui-note is-full">{{ __('quotes.accept_helper') }}</p>
        <div class="is-full"><x-ui.date-input :label="__('quotes.start_date')" wire:model="start_date" /></div>
        @if ($needsName)
            <div class="is-full"><x-ui.input :label="__('quotes.short_name')" wire:model="short_name" :hint="__('quotes.short_name_helper')" maxlength="255" /></div>
        @endif
    </div>
</x-ui.window>
