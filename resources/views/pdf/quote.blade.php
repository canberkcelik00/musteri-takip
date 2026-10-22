<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>{{ __('quotes.pdf.title') }} {{ $quote->number }}</title>
<style>
    @page { margin: 36px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
    h1 { font-size: 22px; margin: 0 0 4px 0; color: #0f1f3d; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; }
    .logo { max-height: 56px; max-width: 200px; margin-bottom: 6px; }
    .company { font-size: 10px; color: #4b5563; line-height: 1.5; }
    .meta { text-align: right; font-size: 10px; line-height: 1.6; }
    .meta strong { font-size: 12px; }
    .label { font-size: 9px; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; }
    .recipient { margin: 22px 0 18px 0; line-height: 1.5; }
    .items th { background: #0f1f3d; color: #ffffff; text-align: left; padding: 6px 8px; font-size: 10px; }
    .items th.price, .items td.price { text-align: right; white-space: nowrap; }
    .items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .items .included td { padding: 1px 8px 1px 22px; border-bottom: none; font-size: 9px; color: #6b7280; }
    .items .included.last td { padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
    .free { color: #15803d; }
    .totals { margin-top: 10px; }
    .totals td { padding: 3px 8px; text-align: right; font-size: 12px; }
    .totals .caption { font-size: 10px; color: #6b7280; }
    .totals .sum { font-weight: bold; font-size: 13px; }
    .notes { margin-top: 22px; line-height: 1.5; white-space: pre-line; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td style="width: 55%;">
            @if ($logo !== null)
                <img class="logo" src="{{ $logo }}" alt="">
            @endif
            <div class="company">
                @if ($company['name'] !== null)<strong>{{ $company['name'] }}</strong><br>@endif
                @if ($company['address'] !== null){!! nl2br(e($company['address'])) !!}<br>@endif
                @if ($company['phone'] !== null){{ $company['phone'] }}<br>@endif
                @if ($company['email'] !== null){{ $company['email'] }}<br>@endif
                @if ($company['tax_office'] !== null || $company['tax_number'] !== null){{ __('quotes.pdf.tax') }}: {{ implode(' / ', array_filter([$company['tax_office'], $company['tax_number']])) }}@endif
            </div>
        </td>
        <td class="meta">
            <h1>{{ __('quotes.pdf.title') }}</h1>
            {{ __('quotes.pdf.number') }}: <strong>{{ $quote->number }}</strong><br>
            {{ __('quotes.pdf.date') }}: {{ $quote->created_at->timezone(config('app.timezone'))->format('d.m.Y') }}<br>
            {{ __('quotes.pdf.valid_until') }}: {{ $quote->valid_until->format('d.m.Y') }}
        </td>
    </tr>
</table>

<div class="recipient">
    <div class="label">{{ __('quotes.pdf.recipient') }}</div>
    @foreach ($recipient as $line)
        {{ $line }}<br>
    @endforeach
</div>

<table class="items">
    <thead>
        <tr>
            <th>{{ __('quotes.pdf.column_name') }}</th>
            <th class="price">{{ __('quotes.pdf.column_price') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
            @php($included = $item->package?->catalogServices ?? collect())
            <tr>
                <td>{{ $item->name }}</td>
                <td class="price">
                    @if ($item->is_free)
                        <span class="free">{{ __('quotes.pdf.free') }}</span>
                    @else
                        {{ $format($item->price, $item->currency) }}
                    @endif
                </td>
            </tr>
            @foreach ($included as $service)
                <tr class="included {{ $loop->last ? 'last' : '' }}">
                    <td colspan="2">– {{ $service->name }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>

<table class="totals">
    @forelse ($totals as $total)
        <tr>
            <td class="caption">{{ $loop->first ? __('quotes.pdf.total') : '' }}</td>
            <td class="sum" style="width: 150px;">{{ $total->format() }}</td>
        </tr>
    @empty
        <tr>
            <td class="caption">{{ __('quotes.pdf.total') }}</td>
            <td class="sum" style="width: 150px;">{{ __('quotes.pdf.free') }}</td>
        </tr>
    @endforelse
</table>

@if ($quote->notes !== null && $quote->notes !== '')
    <div class="notes">
        <div class="label">{{ __('quotes.pdf.notes') }}</div>
        {{ $quote->notes }}
    </div>
@endif
</body>
</html>
