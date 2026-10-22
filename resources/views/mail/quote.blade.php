<x-mail::digest-message>
<p class="dg-title">{{ __('quotes.mail.title') }}</p>
<p class="dg-summary">{{ $contactName !== null ? __('quotes.mail.greeting', ['name' => $contactName]) : __('quotes.mail.greeting_plain') }}</p>
<p class="dg-summary">{{ __('quotes.mail.intro', ['number' => $number, 'date' => $validUntil->format('d.m.Y')]) }}</p>

<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('quotes.mail.column.number') }}</th>
<td class="dg-td">{{ $number }}</td>
</tr>
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('quotes.mail.column.valid_until') }}</th>
<td class="dg-td">{{ $validUntil->format('d.m.Y') }}</td>
</tr>
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('quotes.mail.column.total') }}</th>
<td class="dg-td">{{ $total }}</td>
</tr>
</table>

<p class="dg-hint">{{ __('quotes.mail.reply_note') }}</p>
<p class="dg-hint">{{ __('quotes.mail.closing') }}<br>{{ config('mail.from.name') }}</p>
</x-mail::digest-message>
