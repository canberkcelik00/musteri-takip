{{ config('app.name') }}
{{ __('calendar.mail_title', ['name' => $name]) }}

{{ __('calendar.mail_body', ['by' => $inviterName]) }}

{{ $title }}
{{ __('calendar.mail_when') }}: {{ $when }}
@if ($repeat)
{{ __('calendar.mail_repeat') }}: {{ $repeat }}
@endif
@if ($customer)
{{ __('calendar.mail_customer') }}: {{ $customer }}
@endif
@if ($location)
{{ __('calendar.mail_location') }}: {{ $location }}
@endif
@if ($notes)
{{ $notes }}
@endif

{{ __('calendar.mail_button') }}: {{ $url }}
