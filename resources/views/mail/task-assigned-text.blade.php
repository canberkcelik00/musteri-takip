{{ config('app.name') }}
{{ __('tasks.mail_title', ['name' => $name]) }}

{{ __('tasks.mail_body', ['by' => $assignerName]) }}

{{ $title }}
@if ($customer)
{{ __('tasks.mail_customer') }}: {{ $customer }}
@endif
@if ($dueDate)
{{ __('tasks.mail_due_date') }}: {{ $dueDate }}
@endif
@if ($notes)
{{ $notes }}
@endif

{{ __('tasks.mail_button') }}: {{ $url }}
