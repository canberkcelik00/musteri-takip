<x-mail::digest-message>
<p class="dg-title">{{ __('usage.mail.title') }} – {{ $date->format('d.m.Y') }}</p>
<p class="dg-summary">{{ __('usage.mail.summary', ['count' => $alerts->count()]) }}</p>
<p class="dg-hint">{{ __('usage.mail.intro', ['percent' => $threshold]) }}</p>

@include('mail.usage-alerts', ['alerts' => $alerts, 'customerUrl' => $customerUrl])

<x-mail::button :url="$panelUrl">{{ __('digest.open_panel') }}</x-mail::button>
</x-mail::digest-message>
