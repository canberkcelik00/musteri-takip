@props(['item'])

{{-- One sidebar row: a colored rounded square with a white icon, then the label (docs/UI.md §6 "Menü"). --}}
<a href="{{ $item['url'] }}" wire:navigate.hover class="ui-nav-item" @if ($item['active']) aria-current="page" @endif>
    <span class="ui-nav-icon ui-nav-{{ $item['color'] }}">
        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" aria-hidden="true" />
    </span>
    <span>{{ __($item['label']) }}</span>
</a>
