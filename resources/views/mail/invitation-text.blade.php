{{ config('app.name') }}
{{ __('users.mail_title', ['name' => $name]) }}

{{ __('users.mail_body', ['app' => config('app.name'), 'hours' => $validHours]) }}

{{ __('users.mail_button') }}: {{ $url }}
