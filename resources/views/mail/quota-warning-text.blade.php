{{ config('app.name') }}
{{ __('usage.mail.title') }} – {{ $date->format('d.m.Y') }}

{{ __('usage.mail.intro', ['percent' => $threshold]) }}

@include('mail.usage-alerts-text', ['alerts' => $alerts, 'customerUrl' => $customerUrl])

{{ __('digest.open_panel') }}: {{ $panelUrl }}
