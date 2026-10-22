{{ __('quotes.mail.title') }}

{{ $contactName !== null ? __('quotes.mail.greeting', ['name' => $contactName]) : __('quotes.mail.greeting_plain') }}

{{ __('quotes.mail.intro', ['number' => $number, 'date' => $validUntil->format('d.m.Y')]) }}

{{ __('quotes.mail.column.number') }}: {{ $number }}
{{ __('quotes.mail.column.valid_until') }}: {{ $validUntil->format('d.m.Y') }}
{{ __('quotes.mail.column.total') }}: {{ $total }}

{{ __('quotes.mail.reply_note') }}

{{ __('quotes.mail.closing') }}
{{ config('mail.from.name') }}
