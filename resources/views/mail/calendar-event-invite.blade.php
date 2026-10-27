<x-mail::digest-message>
<p class="dg-title">{{ __('calendar.mail_title', ['name' => $name]) }}</p>
<p class="dg-summary">{{ __('calendar.mail_body', ['by' => $inviterName]) }}</p>

<p class="dg-summary"><strong>{{ $title }}</strong>
<br>{{ __('calendar.mail_when') }}: {{ $when }}
@if ($repeat)
<br>{{ __('calendar.mail_repeat') }}: {{ $repeat }}
@endif
@if ($customer)
<br>{{ __('calendar.mail_customer') }}: {{ $customer }}
@endif
@if ($location)
<br>{{ __('calendar.mail_location') }}: {{ $location }}
@endif
@if ($notes)
<br>{{ $notes }}
@endif
</p>

<x-mail::button :url="$url">{{ __('calendar.mail_button') }}</x-mail::button>
</x-mail::digest-message>
