@php
    use App\Support\Money;

    $newCharges = $digest['new_charges'];
    $freeDecisions = $digest['free_decisions'];
    $overdue = $digest['overdue'];
    $counts = collect([
        'new_charges' => $newCharges->count(),
        'free_decisions' => $freeDecisions->count(),
        'overdue' => $overdue->count(),
    ])->filter()->map(fn (int $count, string $key): string => __('digest.count.'.$key, ['count' => $count]));
    $overdueTotals = Money::formatTotals(Money::sumByCurrency(
        $overdue->map(fn ($charge): Money => new Money($charge->remainingAmount(), $charge->currency)),
    ));
@endphp
<x-mail::message>
<p class="dg-title">{{ __('digest.title.'.$frequency->value) }} – {{ $date->format('d.m.Y') }}</p>
<p class="dg-summary">{{ $counts->implode(' · ') }}</p>

@if ($newCharges->isNotEmpty())
<p class="dg-heading">{{ __('digest.new_charges') }} ({{ $newCharges->count() }})</p>
<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th">{{ __('digest.column.customer') }}</th>
<th class="dg-th">{{ __('digest.column.service') }}</th>
<th class="dg-th dg-num">{{ __('digest.column.amount') }}</th>
<th class="dg-th">{{ __('digest.column.due') }}</th>
</tr>
@foreach ($newCharges as $charge)
@php($creditAmount = (int) $charge->credit_balance_amount)
<tr>
<td class="dg-td"><a href="{{ $customerUrl($charge->customer_id) }}">{{ $charge->customer->short_name }}</a></td>
<td class="dg-td">{{ $charge->description }}
@if ($creditAmount > 0 && $creditAmount < $charge->amount)
<span class="dg-note">{{ __('digest.partly_from_credit', ['amount' => (new Money($creditAmount, $charge->currency))->format()]) }}</span>
@endif
</td>
<td class="dg-td dg-num">{{ (new Money($charge->amount, $charge->currency))->format() }}
@if ($charge->payment_badge !== null && $charge->payment_badge['state'] === 'partially_paid')
<span class="dg-note">{{ __('digest.remaining', ['amount' => (new Money($charge->remainingAmount(), $charge->currency))->format()]) }}</span>
@endif
</td>
<td class="dg-td dg-nowrap">{{ $charge->due_date->format('d.m.Y') }}
@if ($charge->payment_badge !== null)
<br>@include('mail.digest.badge', ['color' => $charge->payment_badge['color'], 'label' => $charge->payment_badge['label']])
@endif
</td>
</tr>
@endforeach
</table>
@endif

@if ($freeDecisions->isNotEmpty())
<p class="dg-heading">{{ __('digest.free_decisions') }} ({{ $freeDecisions->count() }})</p>
<p class="dg-hint">{{ __('digest.free_question') }}</p>
<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th">{{ __('digest.column.customer') }}</th>
<th class="dg-th">{{ __('digest.column.service') }}</th>
<th class="dg-th">{{ __('digest.column.ends') }}</th>
</tr>
@foreach ($freeDecisions as $customerService)
<tr>
<td class="dg-td"><a href="{{ $customerUrl($customerService->customer_id) }}">{{ $customerService->customer->short_name }}</a></td>
<td class="dg-td">{{ $customerService->name }}</td>
<td class="dg-td dg-nowrap">{{ $customerService->end_date->format('d.m.Y') }}</td>
</tr>
@endforeach
</table>
@endif

@if ($overdue->isNotEmpty())
<p class="dg-heading">{{ __('digest.overdue') }} ({{ $overdue->count() }})</p>
<table class="dg-table" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<th class="dg-th">{{ __('digest.column.customer') }}</th>
<th class="dg-th">{{ __('digest.column.service') }}</th>
<th class="dg-th dg-num">{{ __('digest.column.remaining') }}</th>
<th class="dg-th">{{ __('digest.column.overdue') }}</th>
</tr>
@foreach ($overdue as $charge)
<tr>
<td class="dg-td"><a href="{{ $customerUrl($charge->customer_id) }}">{{ $charge->customer->short_name }}</a></td>
<td class="dg-td">{{ $charge->description }}<span class="dg-note">{{ __('digest.column.due') }} {{ $charge->due_date->format('d.m.Y') }}</span></td>
<td class="dg-td dg-num">{{ (new Money($charge->remainingAmount(), $charge->currency))->format() }}</td>
<td class="dg-td dg-nowrap">@include('mail.digest.badge', ['color' => 'danger', 'label' => __('charges.overdue_days', ['count' => $charge->daysOverdue()])])</td>
</tr>
@endforeach
</table>
<p class="dg-total">{{ __('digest.total') }}: {{ $overdueTotals }}</p>
@endif

<x-mail::button :url="$panelUrl">{{ __('digest.open_panel') }}</x-mail::button>
</x-mail::message>
