<x-mail::digest-message>
<p class="dg-title">{{ __('test_mail.title') }}</p>
<p class="dg-summary">{{ __('test_mail.body') }}</p>

<x-mail::button :url="$panelUrl">{{ __('digest.open_panel') }}</x-mail::button>
</x-mail::digest-message>
