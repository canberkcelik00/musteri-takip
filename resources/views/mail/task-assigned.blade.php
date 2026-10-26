<x-mail::digest-message>
<p class="dg-title">{{ __('tasks.mail_title', ['name' => $name]) }}</p>
<p class="dg-summary">{{ __('tasks.mail_body', ['by' => $assignerName]) }}</p>

<p class="dg-summary"><strong>{{ $title }}</strong>
@if ($customer)
<br>{{ __('tasks.mail_customer') }}: {{ $customer }}
@endif
@if ($dueDate)
<br>{{ __('tasks.mail_due_date') }}: {{ $dueDate }}
@endif
@if ($notes)
<br>{{ $notes }}
@endif
</p>

<x-mail::button :url="$url">{{ __('tasks.mail_button') }}</x-mail::button>
</x-mail::digest-message>
