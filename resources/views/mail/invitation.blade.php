<x-mail::digest-message>
<p class="dg-title">{{ __('users.mail_title', ['name' => $name]) }}</p>
<p class="dg-summary">{{ __('users.mail_body', ['app' => config('app.name'), 'hours' => $validHours]) }}</p>

<x-mail::button :url="$url">{{ __('users.mail_button') }}</x-mail::button>
</x-mail::digest-message>
