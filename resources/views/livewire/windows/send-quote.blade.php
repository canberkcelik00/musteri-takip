<x-ui.window :title="$resend ? __('quotes.resend_heading') : __('quotes.send_heading')" submit="send" :submit-label="$resend ? __('quotes.resend') : __('quotes.send')">
    <x-ui.input
        type="email"
        :label="__('quotes.send_recipient')"
        wire:model="recipient_email"
        :hint="$resend ? __('quotes.resend_helper') : __('quotes.send_helper')"
        maxlength="255"
        autofocus
    />
</x-ui.window>
