@props(['amount', 'currency', 'danger' => false])

{{-- An amount in minor units with its currency, formatted by the Money helper ("4.000,50 TL"). --}}
@php
    $currency = $currency instanceof \App\Enums\Currency ? $currency : \App\Enums\Currency::from($currency);
@endphp

<span {{ $attributes->class(['ui-money', 'ui-money-danger' => $danger]) }}>{{ (new \App\Support\Money((int) $amount, $currency))->format() }}</span>
