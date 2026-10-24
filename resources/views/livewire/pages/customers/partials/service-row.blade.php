@php
    use App\Enums\CustomerServiceStatus;
    use App\Support\Ui\Js;
    use Illuminate\Support\Carbon;

    $cancelled = $service->status === CustomerServiceStatus::Cancelled;
    $endingSoon = $service->isEndingSoon($leadDays);
    $domains = $service->domains();
    $others = count($service->otherDomains());
    $statusLabel = ! $cancelled && $endingSoon ? __('customer_services.ending_soon') : $service->status->getLabel();
    $statusTone = $cancelled ? 'gray' : ($endingSoon ? 'amber' : null);
@endphp

<x-ui.row wire:key="service-{{ $service->id }}">
    <td>
        <button type="button" class="ui-link-button ui-cell-title" x-on:click="{!! Js::openWindow('edit-service', ['service' => $service->id]) !!}">{{ $service->name }}</button>
        @if ($domains !== [])
            <span class="ui-sub-line">
                <x-ui.domains :domains="$domains" />@if ($others > 0) +{{ __('customer_services.more_domains', ['count' => $others]) }}@endif
            </span>
        @endif
        @if ($statusTone)
            <span class="ui-below-xl"><x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge></span>
        @endif
        @if ($usageBadge)
            <button type="button" class="ui-link-button ui-below-xl" style="display: block" x-on:click="{!! Js::openWindow('view-usage', ['item' => $usageBadge['item']]) !!}">
                <x-ui.badge :tone="$usageBadge['tone']">{{ $usageBadge['text'] }}</x-ui.badge>
            </button>
        @endif
    </td>
    <td class="ui-from-md">{{ Carbon::parse($service->end_date)->format('d.m.Y') }}</td>
    <td class="is-end">
        @if ($service->is_free)
            {{ __('customer_services.free') }}
        @else
            <x-ui.money :amount="$service->price" :currency="$service->currency" />
        @endif
    </td>
    <td class="ui-from-xl">
        @if ($statusTone)
            <x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge>
        @endif
    </td>
    @if ($cancelled)
        <x-slot:actions>
            <x-ui.button size="sm" class="ui-from-md" x-on:click="{!! Js::openWindow('reactivate-service', ['service' => $service->id]) !!}">{{ __('customer_services.reactivate') }}</x-ui.button>
        </x-slot:actions>
    @endif
</x-ui.row>
