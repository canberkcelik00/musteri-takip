<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th" width="28%" style="width: 28%;">{{ __('digest.column.customer') }}</th>
<th class="dg-th" width="30%" style="width: 30%;">{{ __('usage.column.item') }}</th>
<th class="dg-th dg-num" width="24%" style="width: 24%;">{{ __('usage.column.usage') }}</th>
<th class="dg-th" width="18%" style="width: 18%;">{{ __('usage.column.status') }}</th>
</tr>
@foreach ($alerts as $alert)
<tr>
<td class="dg-td">@if ($alert->isServer())<a href="{{ \App\Support\Ui\Links::servers() }}">{{ __('usage.server_owner', ['name' => $alert->serverName]) }}</a>@else @foreach ($alert->owners as $owner)<a href="{{ $customerUrl($owner['customer_id']) }}">{{ $owner['short_name'] }}</a>@if (! $loop->last), @endif @endforeach @endif</td>
<td class="dg-td">{{ $alert->subject }}</td>
<td class="dg-td dg-num">{{ $alert->usageText() }}<span class="dg-note" style="color: #55555b;">%{{ $alert->percent() }}</span></td>
<td class="dg-td dg-nowrap">@include('mail.digest.badge', ['color' => $alert->level->getColor(), 'label' => $alert->level->getLabel()])</td>
</tr>
@endforeach
</table>
