@props(['heading', 'description' => null])

<h2>{{ $heading }}</h2>
@if ($description)
    <p>{{ $description }}</p>
@endif
<div class="flex justify-center">{{ $slot }}</div>
