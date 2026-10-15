@php
    use App\Support\Money;
@endphp
{{ __('reminders.title') }}

{{ __('reminders.greeting', ['name' => $contactName]) }}

{{ __($hasEnded ? 'reminders.intro_past' : 'reminders.intro', ['service' => $serviceName, 'date' => $endDate->format('d.m.Y')]) }}

{{ __('reminders.column.service') }}: {{ $serviceName }}
{{ __('reminders.column.period') }}: {{ $endDate->format('d.m.Y') }} – {{ $periodEnd->format('d.m.Y') }}
{{ __('reminders.column.amount') }}: {{ (new Money($remaining, $charge->currency))->format() }}
{{ __('reminders.column.due') }}: {{ $dueDate->format('d.m.Y') }}

{{ __('reminders.reply_note') }}

{{ __('reminders.closing') }}
{{ config('mail.from.name') }}
