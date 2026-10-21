@foreach ($alerts as $alert)
- {{ $alert->isServer() ? __('usage.server_owner', ['name' => $alert->serverName]) : collect($alert->owners)->pluck('short_name')->implode(', ') }} | {{ $alert->subject }} | {{ $alert->usageText() }} (%{{ $alert->percent() }}) | {{ $alert->level->getLabel() }}
@if ($alert->isServer())
  {{ \App\Filament\Resources\Servers\ServerResource::getUrl('index', panel: 'panel') }}
@endif
@foreach ($alert->owners as $owner)
  {{ $customerUrl($owner['customer_id']) }}
@endforeach
@endforeach
