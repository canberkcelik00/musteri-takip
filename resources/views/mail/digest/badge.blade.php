@php
    $class = ['success' => 'green', 'warning' => 'amber', 'danger' => 'red'][$color] ?? 'gray';
@endphp
<span class="badge badge-{{ $class }}"><span class="badge-dot">●</span> {{ $label }}</span>
