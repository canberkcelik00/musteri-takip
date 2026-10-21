@php
    use App\Support\Money;
@endphp
{{ config('app.name') }}
{{ __('digest.title.'.$frequency->value) }} – {{ $date->format('d.m.Y') }}
@if ($digest['new_charges']->isNotEmpty())

{{ __('digest.new_charges') }} ({{ $digest['new_charges']->count() }})
@foreach ($digest['new_charges'] as $charge)
- {{ $charge->customer->short_name }} | {{ $charge->description }} | {{ (new Money($charge->amount, $charge->currency))->format() }} | {{ __('digest.column.due') }} {{ $charge->due_date->format('d.m.Y') }}@if ($charge->payment_badge !== null) | {{ $charge->payment_badge['label'] }}@endif

  {{ $customerUrl($charge->customer_id) }}
@endforeach
@endif
@if ($digest['free_decisions']->isNotEmpty())

{{ __('digest.free_decisions') }} ({{ $digest['free_decisions']->count() }})
{{ __('digest.free_question') }}
@foreach ($digest['free_decisions'] as $customerService)
- {{ $customerService->customer->short_name }} | {{ $customerService->name }} | {{ __('digest.column.ends') }} {{ $customerService->end_date->format('d.m.Y') }}
  {{ $customerUrl($customerService->customer_id) }}
@endforeach
@endif
@if ($digest['overdue']->isNotEmpty())

{{ __('digest.overdue') }} ({{ $digest['overdue']->count() }})
@foreach ($digest['overdue'] as $charge)
- {{ $charge->customer->short_name }} | {{ $charge->description }} | {{ __('digest.remaining', ['amount' => (new Money($charge->remainingAmount(), $charge->currency))->format()]) }} | {{ __('charges.overdue_days', ['count' => $charge->daysOverdue()]) }}
  {{ $customerUrl($charge->customer_id) }}
@endforeach
{{ __('digest.total') }}: {{ Money::formatTotals(Money::sumByCurrency($digest['overdue']->map(fn ($charge): Money => new Money($charge->remainingAmount(), $charge->currency)))) }}
@endif
@if ($digest['missing_reminder_email']->isNotEmpty())

{{ __('digest.missing_reminder_email') }} ({{ $digest['missing_reminder_email']->count() }})
@foreach ($digest['missing_reminder_email'] as $charge)
- {{ $charge->customer->short_name }} | {{ $charge->customerService->name }} | {{ __('digest.column.due') }} {{ $charge->due_date->format('d.m.Y') }}
  {{ $customerUrl($charge->customer_id) }}
@endforeach
@endif
@if ($digest['quota_alerts']->isNotEmpty())

{{ __('digest.quota_alerts') }} ({{ $digest['quota_alerts']->count() }})
@include('mail.usage-alerts-text', ['alerts' => $digest['quota_alerts'], 'customerUrl' => $customerUrl])
@endif

{{ __('digest.open_panel') }}: {{ $panelUrl }}
