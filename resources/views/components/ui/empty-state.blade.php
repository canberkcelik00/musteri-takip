@props(['heading', 'description' => null, 'icon' => 'inbox'])

{{-- What an empty list or card says: a quiet icon, what is missing, what to do next, and the action in the slot. --}}
<div class="ui-empty">
    <x-dynamic-component :component="'heroicon-o-'.$icon" class="ui-empty-icon" aria-hidden="true" />
    <h2>{{ $heading }}</h2>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
    <div class="flex justify-center">{{ $slot }}</div>
</div>
