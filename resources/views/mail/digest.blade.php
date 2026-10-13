@php
    use App\Support\Money;
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('digest.subject.'.$frequency->value, ['date' => $date->format('d.m.Y')]) }}</title>
</head>
<body style="margin: 0; padding: 24px 16px; background: #ffffff; color: #1f2937; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.5;">
<div style="max-width: 640px; margin: 0 auto;">
    <h1 style="margin: 0 0 4px; font-size: 20px;">{{ __('digest.title.'.$frequency->value) }}</h1>
    <p style="margin: 0 0 24px; color: #6b7280;">{{ $date->format('d.m.Y') }}</p>

    @if ($digest['new_charges']->isNotEmpty())
        <h2 style="margin: 0 0 8px; font-size: 16px;">{{ __('digest.new_charges') }} ({{ $digest['new_charges']->count() }})</h2>
        <ul style="margin: 0 0 24px; padding-left: 20px;">
            @foreach ($digest['new_charges'] as $charge)
                @php($creditAmount = (int) $charge->credit_balance_amount)
                <li style="margin-bottom: 6px;">
                    <a href="{{ $customerUrl($charge->customer_id) }}" style="color: #1d4ed8; font-weight: bold;">{{ $charge->customer->short_name }}</a>
                    — {{ $charge->description }} · {{ (new Money($charge->amount, $charge->currency))->format() }} · {{ __('digest.due_on', ['date' => $charge->due_date->format('d.m.Y')]) }}
                    @if ($creditAmount >= $charge->amount)
                        · <span style="color: #047857;">{{ __('digest.closed_from_credit') }}</span>
                    @elseif ($creditAmount > 0)
                        · <span style="color: #047857;">{{ __('digest.partly_from_credit', ['amount' => (new Money($creditAmount, $charge->currency))->format()]) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($digest['free_decisions']->isNotEmpty())
        <h2 style="margin: 0 0 8px; font-size: 16px;">{{ __('digest.free_decisions') }} ({{ $digest['free_decisions']->count() }})</h2>
        <p style="margin: 0 0 8px; color: #6b7280;">{{ __('digest.free_question') }}</p>
        <ul style="margin: 0 0 24px; padding-left: 20px;">
            @foreach ($digest['free_decisions'] as $customerService)
                <li style="margin-bottom: 6px;">
                    <a href="{{ $customerUrl($customerService->customer_id) }}" style="color: #1d4ed8; font-weight: bold;">{{ $customerService->customer->short_name }}</a>
                    — {{ $customerService->name }} · {{ __('digest.ends_on', ['date' => $customerService->end_date->format('d.m.Y')]) }}
                </li>
            @endforeach
        </ul>
    @endif

    @if ($digest['overdue']->isNotEmpty())
        <h2 style="margin: 0 0 8px; font-size: 16px;">{{ __('digest.overdue') }} ({{ $digest['overdue']->count() }})</h2>
        <ul style="margin: 0 0 24px; padding-left: 20px;">
            @foreach ($digest['overdue'] as $charge)
                <li style="margin-bottom: 6px;">
                    <a href="{{ $customerUrl($charge->customer_id) }}" style="color: #1d4ed8; font-weight: bold;">{{ $charge->customer->short_name }}</a>
                    — {{ $charge->description }} · {{ __('digest.remaining', ['amount' => (new Money($charge->remainingAmount(), $charge->currency))->format()]) }} · {{ __('digest.due_on', ['date' => $charge->due_date->format('d.m.Y')]) }} · <span style="color: #b91c1c;">{{ __('digest.days_overdue', ['days' => $charge->daysOverdue()]) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <p style="margin: 0;"><a href="{{ $panelUrl }}" style="color: #1d4ed8;">{{ __('digest.open_panel') }}</a></p>
</div>
</body>
</html>
