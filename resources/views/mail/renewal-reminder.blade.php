@php
    use App\Support\Money;
@endphp
<x-mail::digest-message>
<p class="dg-title">{{ __('reminders.title') }}</p>
<p class="dg-summary">{{ __('reminders.greeting', ['name' => $contactName]) }}</p>
<p class="dg-summary">{{ __('reminders.intro', ['service' => $serviceName, 'date' => $endDate->format('d.m.Y')]) }}</p>

<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('reminders.column.service') }}</th>
<td class="dg-td">{{ $serviceName }}</td>
</tr>
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('reminders.column.period') }}</th>
<td class="dg-td">{{ $endDate->format('d.m.Y') }} – {{ $periodEnd->format('d.m.Y') }}</td>
</tr>
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('reminders.column.amount') }}</th>
<td class="dg-td">{{ (new Money($remaining, $charge->currency))->format() }}</td>
</tr>
<tr>
<th class="dg-th" width="35%" style="width: 35%;">{{ __('reminders.column.due') }}</th>
<td class="dg-td">{{ $dueDate->format('d.m.Y') }}</td>
</tr>
</table>

<p class="dg-hint">{{ __('reminders.reply_note') }}</p>
<p class="dg-hint">{{ __('reminders.closing') }}<br>{{ config('mail.from.name') }}</p>
</x-mail::digest-message>
