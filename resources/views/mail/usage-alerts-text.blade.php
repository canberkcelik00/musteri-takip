@foreach ($alerts as $alert)
- {{ collect($alert->owners)->pluck('short_name')->implode(', ') }} | {{ $alert->subject }} | {{ $alert->usageText() }} (%{{ $alert->percent() }}) | {{ $alert->level->getLabel() }}
@foreach ($alert->owners as $owner)
  {{ $customerUrl($owner['customer_id']) }}
@endforeach
@endforeach
