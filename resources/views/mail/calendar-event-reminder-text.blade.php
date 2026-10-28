{{ config('app.name') }}
{{ __('calendar.mail_title', ['name' => $name]) }}

{{ __('calendar.reminder_mail.body') }}

{{ $title }}
{{ __('calendar.mail_when') }}: {{ $when }}
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
